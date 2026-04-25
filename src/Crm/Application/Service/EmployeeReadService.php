<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Employee;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
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
use function ceil;
use function max;
use function method_exists;
use function min;
use function trim;

readonly class EmployeeReadService
{
    public function __construct(
        private EmployeeRepository $employeeRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    public function getList(string $applicationSlug, Request $request): array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
        ];

        $cacheKey = $this->cacheKeyConventionService->buildCrmEmployeeListKey($applicationSlug, $page, $limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $page, $limit, $filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmEmployeeListTag($applicationSlug));
            }

            $esIds = $this->searchIdsFromElastic($filters['q']);
            if ($esIds === []) {
                return $this->emptyList($page, $limit, $filters);
            }

            $effectiveFilters = [
                'q' => $esIds === null ? $filters['q'] : '',
                'ids' => $esIds,
            ];
            $items = $this->employeeRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $effectiveFilters);
            $totalItems = $this->employeeRepository->countScopedByCrm($crm->getId(), $effectiveFilters);

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
                ],
            ];
        });
    }

    public function getDetail(string $applicationSlug, Employee $employee): ?array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $cacheKey = $this->cacheKeyConventionService->buildCrmEmployeeDetailKey($applicationSlug, $employee->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $employee): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmEmployeeListTag($applicationSlug),
                    $this->cacheKeyConventionService->crmEmployeeDetailTag($applicationSlug, $employee->getId()),
                ]);
            }

            return [
                'id' => $employee->getId(),
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'email' => $employee->getEmail(),
                'userId' => $employee->getUserId(),
                'positionName' => $employee->getPositionName(),
                'photo' => $employee->getUser()->getPhoto(),
                'roleName' => $employee->getRoleName(),
                'createdAt' => $employee->getCreatedAt()->format(DATE_ATOM),
                'updatedAt' => $employee->getUpdatedAt()->format(DATE_ATOM),
            ];
        });
    }

    private function searchIdsFromElastic(string $query): ?array
    {
        if ($query === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search('crm_employees', [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'type' => 'phrase_prefix',
                        'fields' => ['firstName^3', 'lastName^3', 'email^2', 'positionName', 'roleName'],
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

    private function emptyList(int $page, int $limit, array $filters): array
    {
        return [
            'items' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => 0,
                'totalPages' => 0,
            ],
            'meta' => [
                'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
            ],
        ];
    }
}
