<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
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

readonly class ProjectReadService
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    public function getList(string $applicationSlug, Request $request): array
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'status' => trim((string)$request->query->get('status', '')),
        ];

        $cacheKey = $this->cacheKeyConventionService->buildCrmProjectListKey($applicationSlug, $page, $limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $page, $limit, $filters): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmProjectListTag($applicationSlug));
            }

            $esIds = $this->searchIdsFromElastic($filters['q']);
            if ($esIds === []) {
                return $this->emptyList($page, $limit, $filters);
            }

            $effectiveFilters = [
                'q' => $esIds === null ? $filters['q'] : '',
                'status' => $filters['status'],
                'ids' => $esIds,
            ];

            $items = $this->projectRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $effectiveFilters);
            $totalItems = $this->projectRepository->countScopedByCrm($crm->getId(), $effectiveFilters);

            return [
                'items' => array_map(fn (array $item): array => $this->crmApiNormalizer->normalizeProjectProjection($item), $items),
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

    public function getDetail(string $applicationSlug, Project $project): ?array
    {
        $cacheKey = $this->cacheKeyConventionService->buildCrmProjectDetailKey($applicationSlug, $project->getId());

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug,  $project): ?array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag([
                    $this->cacheKeyConventionService->crmProjectListTag($applicationSlug),
                    $this->cacheKeyConventionService->crmProjectDetailTag($applicationSlug, $project->getId()),
                ]);
            }

            return [
                'id' => $project->getId(),
                'companyId' => $project->getCompany()?->getId(),
                'name' => $project->getName(),
                'code' => $project->getCode(),
                'description' => $project->getDescription(),
                'status' => $project->getStatus()->value,
                'startedAt' => $project->getStartedAt()?->format(DATE_ATOM),
                'dueAt' => $project->getDueAt()?->format(DATE_ATOM),
                'attachments' => $project->getAttachments(),
                'wikiPages' => $project->getWikiPages(),
                'tasks' => array_map(static fn (Task $task): array => [
                    'id' => $task->getId(),
                    'TITLE' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus()->value,
                    'dueAt' => $task->getDueAt()?->format(DATE_ATOM),
                ], $project->getTasks()->toArray()),
                'assignees' => array_map(static fn ($assignee): array => [
                    'id' => $assignee->getId(),
                    'email' => $assignee->getEmail(),
                    'firstName' => $assignee->getFirstName(),
                    'lastName' => $assignee->getLastName(),
                    'photo' => $assignee->getPhoto(),
                ], $project->getAssignees()->toArray()),
            ];
        });
    }

    private function searchIdsFromElastic(string $query): ?array
    {
        if ($query === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search('crm_projects', [
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'type' => 'phrase_prefix',
                        'fields' => ['name^3', 'code^2', 'description', 'status'],
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
