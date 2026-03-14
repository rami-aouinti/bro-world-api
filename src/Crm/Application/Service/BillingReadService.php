<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Infrastructure\Repository\BillingRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use DateTimeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_values;
use function ceil;
use function max;
use function method_exists;
use function min;
use function trim;

readonly class BillingReadService
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /** @return array<string,mixed> */
    public function getList(string $applicationSlug, Request $request): array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'companyId' => trim((string) $request->query->get('companyId', '')),
        ];

        $cacheKey = $this->cacheKeyConventionService->buildCrmBillingListKey($applicationSlug, $page, $limit, $filters);

        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $page, $limit, $filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmBillingListTag($applicationSlug));
            }

            $esFilters = $this->applyEsFilter($filters);
            $items = $this->billingRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $esFilters);
            $totalItems = $this->billingRepository->countScopedByCrm($crm->getId(), $esFilters);

            return [
                'items' => array_map(fn (array $row): array => $this->normalizeProjection($row), $items),
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
                ],
            ];
        });

        return $result;
    }

    /** @return array<string,mixed>|null */
    public function getDetail(string $applicationSlug, string $billingId): ?array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $cacheKey = $this->cacheKeyConventionService->buildCrmBillingDetailKey($applicationSlug, $billingId);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $billingId): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmBillingListTag($applicationSlug),
                    $this->cacheKeyConventionService->crmBillingDetailTag($applicationSlug, $billingId),
                ]);
            }

            $entity = $this->billingRepository->findOneScopedById($billingId, $crm->getId());
            if ($entity === null) {
                return null;
            }

            return [
                'id' => $entity->getId(),
                'companyId' => $entity->getCompany()?->getId(),
                'label' => $entity->getLabel(),
                'amount' => $entity->getAmount(),
                'currency' => $entity->getCurrency(),
                'status' => $entity->getStatus(),
                'dueAt' => $entity->getDueAt()?->format(DATE_ATOM),
                'paidAt' => $entity->getPaidAt()?->format(DATE_ATOM),
            ];
        });
    }

    /** @param array{q:string,status:string,companyId:string} $filters
     * @return array{q:string,status:string,companyId:string}
     */
    private function applyEsFilter(array $filters): array
    {
        if ($filters['q'] === '') {
            return $filters;
        }

        try {
            $response = $this->elasticsearchService->search('crm_billings', [
                'query' => [
                    'multi_match' => [
                        'query' => $filters['q'],
                        'type' => 'phrase_prefix',
                        'fields' => ['label^3', 'status', 'companyId'],
                    ],
                ],
                '_source' => ['label'],
            ], 0, 1);

            if (($response['hits']['total']['value'] ?? 0) === 0) {
                return ['q' => '__no_match__', 'status' => $filters['status'], 'companyId' => $filters['companyId']];
            }
        } catch (Throwable) {
            return $filters;
        }

        return $filters;
    }

    /** @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function normalizeProjection(array $item): array
    {
        return [
            'id' => (string) ($item['id'] ?? ''),
            'companyId' => $item['companyId'] ?? null,
            'label' => (string) ($item['label'] ?? ''),
            'amount' => isset($item['amount']) ? (float) $item['amount'] : 0.0,
            'currency' => (string) ($item['currency'] ?? 'EUR'),
            'status' => (string) ($item['status'] ?? 'pending'),
            'dueAt' => $this->normalizeDateValue($item['dueAt'] ?? null),
            'paidAt' => $this->normalizeDateValue($item['paidAt'] ?? null),
        ];
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
