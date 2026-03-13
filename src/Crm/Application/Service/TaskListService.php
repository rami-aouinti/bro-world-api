<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Projection\CrmTaskProjection;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

readonly class TaskListService
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
        private CrmApplicationScopeResolver $applicationScopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws \JsonException
     */
    public function getList(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'title' => trim((string)$request->query->get('title', '')),
            'status' => trim((string)$request->query->get('status', '')),
            'priority' => trim((string)$request->query->get('priority', '')),
        ];
        $applicationSlug = (string)$request->attributes->get('applicationSlug', '');
        $crm = $this->applicationScopeResolver->resolveOrFail($applicationSlug);
        $cacheKey = $this->cacheKeyConventionService->buildCrmTaskListKey($page, $limit, array_merge($filters, [
            'applicationSlug' => $applicationSlug,
        ]));

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters, $page, $limit, $crm): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmTaskListTag());
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds === []) {
                return [
                    'items' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'totalItems' => 0,
                        'totalPages' => 0,
                    ],
                    'meta' => [
                        'module' => 'crm',
                    ],
                ];
            }

            $qb = $this->taskRepository->createQueryBuilder('task')->leftJoin('task.project', 'project')->leftJoin('task.sprint', 'sprint')
                ->leftJoin('project.company', 'company')
                ->leftJoin('task.assignees', 'assignee')->addSelect('assignee')
                ->andWhere('company.crm = :crm')->setParameter('crm', $crm->getId(), UuidBinaryOrderedTimeType::NAME)
                ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->orderBy('task.createdAt', 'DESC');

            if ($filters['title'] !== '') {
                $qb->andWhere('LOWER(task.title) LIKE LOWER(:title)')->setParameter('title', '%' . $filters['title'] . '%');
            }
            if ($filters['status'] !== '') {
                $qb->andWhere('task.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['priority'] !== '') {
                $qb->andWhere('task.priority = :priority')->setParameter('priority', $filters['priority']);
            }
            if ($esIds !== null) {
                $qb->andWhere('task.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $items = array_map(fn (Task $task): array => $this->crmApiNormalizer->normalizeTask($task), $qb->getQuery()->getResult());

            $countQb = $this->taskRepository->createQueryBuilder('task')->select('COUNT(task.id)')
                ->leftJoin('task.project', 'project')
                ->leftJoin('project.company', 'company')
                ->andWhere('company.crm = :crm')->setParameter('crm', $crm->getId(), UuidBinaryOrderedTimeType::NAME);
            if ($filters['title'] !== '') {
                $countQb->andWhere('LOWER(task.title) LIKE LOWER(:title)')->setParameter('title', '%' . $filters['title'] . '%');
            }
            if ($filters['status'] !== '') {
                $countQb->andWhere('task.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['priority'] !== '') {
                $countQb->andWhere('task.priority = :priority')->setParameter('priority', $filters['priority']);
            }
            if ($esIds !== null) {
                $countQb->andWhere('task.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'module' => 'crm',
                ],
            ];
        });

        $result['meta']['filters'] = array_filter($filters, static fn (string $value): bool => $value !== '');

        return $result;
    }

    /** @param array<string,string> $filters
     * @return array<int,string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['q'] === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search(CrmTaskProjection::INDEX_NAME, [
                'query' => [
                    'multi_match' => [
                        'query' => $filters['q'],
                        'type' => 'phrase_prefix',
                        'fields' => ['title^3', 'projectName^2', 'sprintName', 'taskRequests'],
                    ],
                ],
                '_source' => ['id'],
            ], 0, 200);
        } catch (Throwable) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
    }
}
