<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

class ApplicationListService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheInterface $cache,
        private readonly ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /** @return array<string, mixed> */
    public function getPublicList(Request $request): array
    {
        return $this->getList($request, null);
    }

    /** @return array<string, mixed> */
    public function getPrivateList(Request $request, User $loggedInUser): array
    {
        return $this->getList($request, $loggedInUser);
    }

    /** @return array<string, mixed> */
    private function getList(Request $request, ?User $loggedInUser): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $filters = [
            'title' => trim((string) $request->query->get('title', '')),
            'description' => trim((string) $request->query->get('description', '')),
            'platformName' => trim((string) $request->query->get('platformName', '')),
            'platformKey' => trim((string) $request->query->get('platformKey', '')),
        ];

        $cacheKey = 'application_list_' . md5((string) json_encode([
            'userId' => $loggedInUser?->getId(),
            'page' => $page,
            'limit' => $limit,
            'filters' => $filters,
        ]));

        /** @var array<string, mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($loggedInUser, $filters, $page, $limit): array {
            $item->expiresAfter(120);

            $qb = $this->entityManager
                ->getRepository(Application::class)
                ->createQueryBuilder('application')
                ->leftJoin('application.platform', 'platform')
                ->leftJoin('application.user', 'user')
                ->leftJoin('application.applicationPlugins', 'applicationPlugin')
                ->leftJoin('applicationPlugin.plugin', 'plugin')
                ->addSelect('platform')
                ->addSelect('user')
                ->addSelect('applicationPlugin')
                ->addSelect('plugin');

            if ($loggedInUser === null) {
                $qb->where('application.private = :publicApplication')
                    ->setParameter('publicApplication', false);
            } else {
                $qb->where('application.private = :publicApplication')
                    ->orWhere('application.user = :loggedInUser')
                    ->setParameter('publicApplication', false)
                    ->setParameter('loggedInUser', $loggedInUser);
            }

            if ($filters['platformKey'] !== '') {
                $qb->andWhere('LOWER(platform.platformKey) = :platformKey')
                    ->setParameter('platformKey', mb_strtolower($filters['platformKey']));
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds !== null) {
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

                $qb->andWhere('application.id IN (:esIds)')
                    ->setParameter('esIds', $esIds);
            }

            if ($filters['title'] !== '') {
                $qb->andWhere('LOWER(application.title) LIKE :title')
                    ->setParameter('title', '%' . mb_strtolower($filters['title']) . '%');
            }

            if ($filters['description'] !== '') {
                $qb->andWhere('LOWER(application.description) LIKE :description')
                    ->setParameter('description', '%' . mb_strtolower($filters['description']) . '%');
            }

            if ($filters['platformName'] !== '') {
                $qb->andWhere('LOWER(platform.name) LIKE :platformName')
                    ->setParameter('platformName', '%' . mb_strtolower($filters['platformName']) . '%');
            }

            $qb->orderBy('application.title', 'ASC')
                ->addOrderBy('application.id', 'ASC');

            $query = $qb
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery();

            $paginator = new Paginator($query, true);
            $totalItems = $paginator->count();

            $items = [];
            /** @var Application $application */
            foreach ($paginator as $application) {
                $pluginKeys = [];
                foreach ($application->getApplicationPlugins() as $applicationPlugin) {
                    $pluginKey = $applicationPlugin->getPlugin()?->getPluginKeyValue();
                    if ($pluginKey !== null) {
                        $pluginKeys[$pluginKey] = true;
                    }
                }

                $items[] = [
                    'id' => $application->getId(),
                    'title' => $application->getTitle(),
                    'slug' => $application->getSlug(),
                    'description' => $application->getDescription(),
                    'photo' => $application->getPhoto(),
                    'status' => $application->getStatus()->value,
                    'private' => $application->isPrivate(),
                    'platformId' => $application->getPlatform()?->getId(),
                    'platformName' => $application->getPlatform()?->getName(),
                    'platformKey' => $application->getPlatform()?->getPlatformKeyValue(),
                    'pluginKeys' => array_keys($pluginKeys),
                    'author' => [
                        'id' => $application->getUser()?->getId(),
                        'firstName' => $application->getUser()?->getFirstName() ?? '',
                        'lastName' => $application->getUser()?->getLastName() ?? '',
                        'photo' => $application->getUser()?->getPhoto() ?? '',
                    ],
                    'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                    'isOwner' => $loggedInUser !== null && $application->getUser()?->getId() === $loggedInUser->getId(),
                ];
            }

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 0,
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
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['title'] === '' && $filters['description'] === '' && $filters['platformName'] === '') {
            return null;
        }

        try {
            $must = [];

            if ($filters['title'] !== '') {
                $must[] = ['match_phrase_prefix' => ['title' => $filters['title']]];
            }
            if ($filters['description'] !== '') {
                $must[] = ['match_phrase_prefix' => ['description' => $filters['description']]];
            }
            if ($filters['platformName'] !== '') {
                $must[] = ['match_phrase_prefix' => ['platformName' => $filters['platformName']]];
            }

            $response = $this->elasticsearchService->search(
                ElasticsearchServiceInterface::INDEX_PREFIX . '_*',
                [
                    'query' => ['bool' => ['must' => $must]],
                    '_source' => ['id'],
                ],
                0,
                1000,
            );

            if (!is_array($response) || !isset($response['hits']['hits']) || !is_array($response['hits']['hits'])) {
                return null;
            }

            $ids = [];
            foreach ($response['hits']['hits'] as $hit) {
                if (is_array($hit) && isset($hit['_source']['id']) && is_string($hit['_source']['id'])) {
                    $ids[] = $hit['_source']['id'];
                }
            }

            return array_values(array_unique($ids));
        } catch (Throwable) {
            return null;
        }
    }
}
