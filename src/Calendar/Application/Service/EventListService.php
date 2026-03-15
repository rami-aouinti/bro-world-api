<?php

declare(strict_types=1);

namespace App\Calendar\Application\Service;

use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Domain\Service\Interfaces\MetricsCounterInterface;
use App\User\Domain\Entity\User;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;

final readonly class EventListService
{
    private const int ELASTIC_IDS_LIMIT = 1000;
    private const string ELASTIC_FALLBACK_COUNT_METRIC = 'calendar.elastic_fallback.count';

    public function __construct(
        private EventRepositoryInterface $eventRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
        private LoggerInterface $logger,
        private MetricsCounterInterface $metricsCounter,
    ) {
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByUser(User $user, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('user', $filters, $page, $limit, $user, null);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByApplicationSlug(string $applicationSlug, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('application_public', $filters, $page, $limit, null, $applicationSlug);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->getList('application_private', $filters, $page, $limit, $user, $applicationSlug);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpcoming(User $user, ?string $applicationSlug = null, int $limit = 3): array
    {
        $safeLimit = max(1, min(20, $limit));
        $events = $applicationSlug !== null && $applicationSlug !== ''
            ? $this->eventRepository->findUpcomingByApplicationSlugAndUser($applicationSlug, $user, $safeLimit)
            : $this->eventRepository->findUpcomingPrivateByUser($user, $safeLimit);

        return $this->normalizeEvents($events);
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    private function getList(string $accessContext, array $filters, int $page, int $limit, ?User $user, ?string $applicationSlug): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $filters = [
            'title' => trim((string)($filters['title'] ?? '')),
            'description' => trim((string)($filters['description'] ?? '')),
            'location' => trim((string)($filters['location'] ?? '')),
        ];

        $cachePayload = [
            'accessContext' => $accessContext,
            'userId' => $user?->getId(),
            'applicationSlug' => $applicationSlug,
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ];

        $cacheKey = $user !== null
            ? $this->cacheKeyConventionService->buildPrivateEventKey($user->getUsername(), $cachePayload)
            : 'event_list_' . md5((string)json_encode($cachePayload, JSON_THROW_ON_ERROR));

        /** @var array<string, mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($accessContext, $user, $applicationSlug, $filters, $page, $limit): array {
            $item->expiresAfter(120);
            if ($user !== null && method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPrivateEvents($user->getId()));
            }
            if ($applicationSlug !== null && $applicationSlug !== '' && method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicEventsByApplication($applicationSlug));
            }

            $esIds = $this->searchIdsFromElastic($filters, $accessContext);
            if ($esIds === []) {
                return [
                    'items' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'totalItems' => 0,
                        'totalPages' => 0,
                    ],
                ];
            }

            if ($accessContext === 'user') {
                $events = $this->eventRepository->findByUser($user, $filters, $page, $limit, $esIds);
                $totalItems = $this->eventRepository->countByUser($user, $filters, $esIds);
            } elseif ($accessContext === 'application_private') {
                $events = $this->eventRepository->findByApplicationSlugAndUser($applicationSlug, $user, $filters, $page, $limit, $esIds);
                $totalItems = $this->eventRepository->countByApplicationSlugAndUser($applicationSlug, $user, $filters, $esIds);
            } else {
                $events = $this->eventRepository->findByApplicationSlug($applicationSlug, $filters, $page, $limit, $esIds);
                $totalItems = $this->eventRepository->countByApplicationSlug($applicationSlug, $filters, $esIds);
            }

            return [
                'items' => $this->normalizeEvents($events),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
            ];
        });

        $result['filters'] = array_filter($filters, static fn (string $value): bool => $value !== '');

        return $result;
    }

    /**
     * @param array<string, string> $filters
     *
     * @return array<int, string>|null
     */
    private function searchIdsFromElastic(array $filters, ?string $accessContext = null): ?array
    {
        if ($filters['title'] === '' && $filters['description'] === '' && $filters['location'] === '') {
            return null;
        }

        try {
            $must = [];
            if ($filters['title'] !== '') {
                $must[] = [
                    'match_phrase_prefix' => [
                        'title' => $filters['title'],
                    ],
                ];
            }
            if ($filters['description'] !== '') {
                $must[] = [
                    'match_phrase_prefix' => [
                        'description' => $filters['description'],
                    ],
                ];
            }
            if ($filters['location'] !== '') {
                $must[] = [
                    'match_phrase_prefix' => [
                        'location' => $filters['location'],
                    ],
                ];
            }

            $response = $this->elasticsearchService->search(
                ElasticsearchServiceInterface::INDEX_PREFIX . '_*',
                [
                    'query' => [
                        'bool' => [
                            'must' => $must,
                        ],
                    ],
                    'track_total_hits' => true,
                    '_source' => ['id'],
                ],
                0,
                self::ELASTIC_IDS_LIMIT,
            );

            if (!is_array($response) || !isset($response['hits']['hits']) || !is_array($response['hits']['hits'])) {
                return null;
            }

            $totalHits = $this->extractTotalHits($response);
            if ($totalHits !== null && $totalHits > self::ELASTIC_IDS_LIMIT) {
                $this->incrementElasticFallbackCounter('too_many_hits');

                return null;
            }

            $ids = [];
            foreach ($response['hits']['hits'] as $hit) {
                if (is_array($hit) && isset($hit['_source']['id']) && is_string($hit['_source']['id'])) {
                    $ids[] = $hit['_source']['id'];
                }
            }

            return array_values(array_unique($ids));
        } catch (Throwable $exception) {
            $this->incrementElasticFallbackCounter('exception');

            $this->logger->warning('Unable to search event ids from Elasticsearch, fallback to repository filters.', [
                'filterTypes' => array_values(array_keys(array_filter($filters, static fn (string $value): bool => $value !== ''))),
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
                'accessContext' => $accessContext,
            ]);

            return null;
        }
    }

    private function incrementElasticFallbackCounter(string $reason): void
    {
        $this->metricsCounter->increment(self::ELASTIC_FALLBACK_COUNT_METRIC, [
            'reason' => $reason,
        ]);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractTotalHits(array $response): ?int
    {
        if (!isset($response['hits']['total'])) {
            return null;
        }

        if (is_int($response['hits']['total'])) {
            return $response['hits']['total'];
        }

        if (
            is_array($response['hits']['total'])
            && isset($response['hits']['total']['value'])
            && is_int($response['hits']['total']['value'])
        ) {
            return $response['hits']['total']['value'];
        }

        return null;
    }

    /**
     * @param array<int, Event> $events
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeEvents(array $events): array
    {
        return array_map(static function (Event $event): array {
            return [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'startAt' => $event->getStartAt()->format(DATE_ATOM),
                'endAt' => $event->getEndAt()->format(DATE_ATOM),
                'status' => $event->getStatusValue(),
                'visibility' => $event->getVisibilityValue(),
                'location' => $event->getLocation(),
                'isAllDay' => $event->isAllDay(),
                'timezone' => $event->getTimezone(),
                'isCancelled' => $event->isCancelled(),
                'url' => $event->getUrl(),
                'color' => $event->getColor(),
                'backgroundColor' => $event->getBackgroundColor(),
                'borderColor' => $event->getBorderColor(),
                'textColor' => $event->getTextColor(),
                'organizerName' => $event->getOrganizerName(),
                'organizerEmail' => $event->getOrganizerEmail(),
                'attendees' => $event->getAttendees(),
                'rrule' => $event->getRrule(),
                'recurrenceExceptions' => $event->getRecurrenceExceptions(),
                'recurrenceEndAt' => $event->getRecurrenceEndAt()?->format(DATE_ATOM),
                'recurrenceCount' => $event->getRecurrenceCount(),
                'reminders' => $event->getReminders(),
                'metadata' => $event->getMetadata(),
                'calendarId' => $event->getCalendar()?->getId(),
                'applicationSlug' => $event->getCalendar()?->getApplication()?->getSlug(),
                'userId' => $event->getUser()?->getId(),
            ];
        }, $events);
    }
}
