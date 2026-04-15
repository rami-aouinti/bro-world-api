<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Infrastructure\Repository\ContactRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function method_exists;
use function ceil;

readonly class ContactReadService
{
    public function __construct(
        private ContactRepository $contactRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CrmListRequestHelper $listRequestHelper,
        private CrmListResponseFactory $listResponseFactory,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function list(string $applicationSlug, Request $request): array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);
        $filters = $queryOptions->filters;

        $cacheKey = $this->cacheKeyConventionService->buildCrmContactListKey($applicationSlug, $queryOptions->page, $queryOptions->limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $queryOptions, $filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmContactListTag($applicationSlug));
            }

            $esIds = $this->searchIdsFromElastic($filters['q']);
            if ($esIds === []) {
                return $this->listResponseFactory->create($queryOptions, 0, []);
            }

            $items = $this->contactRepository->findScopedProjection($crm->getId(), $queryOptions->limit, $queryOptions->offset(), [
                'q' => $esIds === null ? $filters['q'] : '',
                'ids' => $esIds,
            ]);
            $totalItems = $this->contactRepository->countScopedByCrm($crm->getId(), [
                'q' => $esIds === null ? $filters['q'] : '',
                'ids' => $esIds,
            ]);

            return $this->listResponseFactory->create(
                $queryOptions,
                $totalItems,
                array_map(fn (array $row): array => $this->normalizeProjection($row), $items),
            );
        });
    }

    /**
     * @return array<string,mixed>|null
     * @throws InvalidArgumentException
     */
    public function getDetail(string $applicationSlug, string $contactId): ?array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $cacheKey = $this->cacheKeyConventionService->buildCrmContactDetailKey($applicationSlug, $contactId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $contactId): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmContactListTag($applicationSlug),
                    $this->cacheKeyConventionService->crmContactDetailTag($applicationSlug, $contactId),
                ]);
            }

            $contact = $this->contactRepository->findOneScopedById($contactId, $crm->getId());
            if ($contact === null) {
                return null;
            }

            return [
                'id' => $contact->getId(),
                'companyId' => $contact->getCompany()?->getId(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'email' => $contact->getEmail(),
                'phone' => $contact->getPhone(),
                'jobTitle' => $contact->getJobTitle(),
                'city' => $contact->getCity(),
                'score' => $contact->getScore(),
            ];
        });
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function listGlobal(Request $request): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);
        $filters = $queryOptions->filters;

        $cacheKey = $this->cacheKeyConventionService->buildCrmContactListKey('general', $queryOptions->page, $queryOptions->limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($queryOptions, $filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmContactListTag('general'));
            }

            $esIds = $this->searchIdsFromElastic($filters['q']);
            if ($esIds === []) {
                return $this->listResponseFactory->create($queryOptions, 0, []);
            }

            $items = $this->contactRepository->findBy([], ['createdAt' => 'DESC'], $queryOptions->limit, $queryOptions->offset());
            $totalItems = (int)count($this->contactRepository->findAll());

            return [
                'items' => array_map(static fn ($contact): array => [
                    'id' => $contact->getId(),
                    'companyId' => $contact->getCompany()?->getId(),
                    'firstName' => $contact->getFirstName(),
                    'lastName' => $contact->getLastName(),
                    'email' => $contact->getEmail(),
                    'phone' => $contact->getPhone(),
                    'jobTitle' => $contact->getJobTitle(),
                    'city' => $contact->getCity(),
                    'score' => $contact->getScore(),
                ], $items),
                'pagination' => [
                    'page' => $queryOptions->page,
                    'limit' => $queryOptions->limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $queryOptions->limit) : 0,
                ],
                'meta' => [
                    'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
                ],
            ];
        });
    }

    /**
     * @return array<string,mixed>|null
     * @throws InvalidArgumentException
     */
    public function getGlobalDetail(string $contactId): ?array
    {
        $cacheKey = $this->cacheKeyConventionService->buildCrmContactDetailKey('general', $contactId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($contactId): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmContactListTag('general'),
                    $this->cacheKeyConventionService->crmContactDetailTag('general', $contactId),
                ]);
            }

            $contact = $this->contactRepository->find($contactId);
            if ($contact === null) {
                return null;
            }

            return [
                'id' => $contact->getId(),
                'companyId' => $contact->getCompany()?->getId(),
                'firstName' => $contact->getFirstName(),
                'lastName' => $contact->getLastName(),
                'email' => $contact->getEmail(),
                'phone' => $contact->getPhone(),
                'jobTitle' => $contact->getJobTitle(),
                'city' => $contact->getCity(),
                'score' => $contact->getScore(),
            ];
        });
    }

    private function searchIdsFromElastic(string $query): ?array
    {
        if ($query === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search('crm_contacts', [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'type' => 'phrase_prefix',
                        'fields' => ['firstName^3', 'lastName^3', 'email^2', 'phone', 'jobTitle', 'city'],
                    ],
                ],
                '_source' => ['id'],
            ], 0, 500);

            $hits = $response['hits']['hits'] ?? [];

            return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param array<string,mixed> $item
     */
    private function normalizeProjection(array $item): array
    {
        return [
            'id' => (string)($item['id'] ?? ''),
            'companyId' => $item['companyId'] ?? null,
            'firstName' => (string)($item['firstName'] ?? ''),
            'lastName' => (string)($item['lastName'] ?? ''),
            'email' => (string)($item['email'] ?? ''),
            'phone' => (string)($item['phone'] ?? ''),
            'jobTitle' => (string)($item['jobTitle'] ?? ''),
            'city' => (string)($item['city'] ?? ''),
            'score' => isset($item['score']) ? (int)$item['score'] : null,
        ];
    }
}
