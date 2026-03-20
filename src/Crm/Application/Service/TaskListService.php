<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Projection\CrmTaskProjection;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function ceil;
use function count;
use function max;
use function method_exists;
use function microtime;
use function min;
use function trim;

readonly class TaskListService
{
    private const int ELASTIC_PAGE_SIZE = 200;

    public function __construct(
        private TaskRepository $taskRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
        private CrmApplicationScopeResolver $applicationScopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
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

        $cacheKey = $this->cacheKeyConventionService->buildCrmTaskListKey(
            $page,
            $limit,
            array_merge($filters, [
                'applicationSlug' => $applicationSlug,
            ])
        );

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $filters, $page, $limit, $crm): array {
            $startAt = microtime(true);
            $item->expiresAfter(120);

            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmTaskListTag($applicationSlug));
            }

            $esIds = $this->searchIdsFromElastic($filters);

            if ($esIds === []) {
                return $this->emptyResult($page, $limit);
            }

            $ids = $this->findTaskIdsForPage($crm->getId(), $filters, $page, $limit, $esIds);

            if ($ids === []) {
                return $this->emptyResult($page, $limit);
            }

            $tasks = $this->findTasksWithRelationsByIds($ids);

            $tasksById = [];
            foreach ($tasks as $task) {
                $tasksById[$task->getId()] = $task;
            }

            $orderedTasks = [];
            foreach ($ids as $id) {
                if (isset($tasksById[$id])) {
                    $orderedTasks[] = $tasksById[$id];
                }
            }

            $items = array_map(
                fn (Task $task): array => $this->crmApiNormalizer->normalizeTask($task),
                $orderedTasks
            );

            $totalItems = $this->countFilteredTasks($crm->getId(), $filters, $esIds);

            $this->logger->info('CRM task list query completed', [
                'applicationSlug' => $applicationSlug,
                'page' => $page,
                'limit' => $limit,
                'filters' => array_keys(array_filter($filters, static fn (string $value): bool => $value !== '')),
                'elasticIdsCount' => $esIds !== null ? count($esIds) : null,
                'resultSetSize' => count($items),
                'totalItems' => $totalItems,
                'durationMs' => (int)((microtime(true) - $startAt) * 1000),
            ]);

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

        $result['meta']['filters'] = array_filter(
            $filters,
            static fn (string $value): bool => $value !== ''
        );

        return $result;
    }

    /**
     * @param array<string,string> $filters
     * @param list<string>|null $esIds
     * @return list<string>
     */
    private function findTaskIdsForPage(
        string $crmId,
        array $filters,
        int $page,
        int $limit,
        ?array $esIds,
    ): array {
        $qb = $this->taskRepository->createQueryBuilder('task')
            ->select('task.id AS id')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('task.createdAt', 'DESC');

        $this->applyStandardFilters($qb, $filters);
        $this->applyBinaryUuidIdsFilter($qb, 'task.id', $esIds, 'es_task_id_');

        /** @var list<array{id: string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string)$row['id'],
            $rows
        ));
    }

    /**
     * @param list<string> $ids
     * @return list<Task>
     */
    private function findTasksWithRelationsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->taskRepository->createQueryBuilder('task')
            ->distinct()
            ->leftJoin('task.project', 'project')->addSelect('project')
            ->leftJoin('task.sprint', 'sprint')->addSelect('sprint')
            ->leftJoin('project.company', 'company')->addSelect('company');

        $this->applyBinaryUuidIdsFilter($qb, 'task.id', $ids, 'task_id_');

        /** @var list<Task> $tasks */
        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string,string> $filters
     * @param list<string>|null $esIds
     */
    private function countFilteredTasks(string $crmId, array $filters, ?array $esIds): int
    {
        $qb = $this->taskRepository->createQueryBuilder('task')
            ->select('COUNT(DISTINCT task.id)')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $this->applyStandardFilters($qb, $filters);
        $this->applyBinaryUuidIdsFilter($qb, 'task.id', $esIds, 'count_task_id_');

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array<string,string> $filters
     */
    private function applyStandardFilters(QueryBuilder $qb, array $filters): void
    {
        if (($filters['title'] ?? '') !== '') {
            $qb->andWhere('LOWER(task.title) LIKE LOWER(:title)')
                ->setParameter('title', '%' . $filters['title'] . '%');
        }

        if (($filters['status'] ?? '') !== '') {
            $qb->andWhere('task.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (($filters['priority'] ?? '') !== '') {
            $qb->andWhere('task.priority = :priority')
                ->setParameter('priority', $filters['priority']);
        }
    }

    /**
     * @param list<string>|null $ids
     */
    private function applyBinaryUuidIdsFilter(
        QueryBuilder $qb,
        string $field,
        ?array $ids,
        string $parameterPrefix,
    ): void {
        if ($ids === null) {
            return;
        }

        if ($ids === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        $binaryIds = array_values(array_unique(array_filter(array_map(
            static fn (string $id): ?string => Uuid::isValid($id) ? Uuid::fromString($id)->getBytes() : null,
            $ids,
        ))));

        if ($binaryIds === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        $parameterName = $parameterPrefix . 'ids';
        $qb->andWhere($field . ' IN (:' . $parameterName . ')')
            ->setParameter($parameterName, $binaryIds, ArrayParameterType::BINARY);
    }

    /**
     * @return array<string,mixed>
     */
    private function emptyResult(int $page, int $limit): array
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
                'module' => 'crm',
            ],
        ];
    }

    /**
     * @param array<string,string> $filters
     * @return array<int,string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['q'] === '') {
            return null;
        }

        try {
            $ids = [];
            $searchAfter = null;

            do {
                $body = [
                    'query' => [
                        'multi_match' => [
                            'query' => $filters['q'],
                            'type' => 'phrase_prefix',
                            'fields' => ['title^3', 'projectName^2', 'sprintName', 'taskRequests'],
                        ],
                    ],
                    'sort' => [
                        [
                            '_doc' => 'asc',
                        ],
                    ],
                    'track_total_hits' => true,
                    '_source' => ['id'],
                ];

                if ($searchAfter !== null) {
                    $body['search_after'] = $searchAfter;
                }

                $response = $this->elasticsearchService->search(
                    CrmTaskProjection::INDEX_NAME,
                    $body,
                    0,
                    self::ELASTIC_PAGE_SIZE,
                );

                $hits = $response['hits']['hits'] ?? [];
                foreach ($hits as $hit) {
                    $id = $hit['_source']['id'] ?? $hit['_id'] ?? null;
                    if (is_string($id) && $id !== '') {
                        $ids[] = $id;
                    }
                }

                $lastHit = $hits !== [] ? $hits[count($hits) - 1] : null;
                $searchAfter = is_array($lastHit) ? ($lastHit['sort'] ?? null) : null;
            } while ($hits !== [] && count($hits) === self::ELASTIC_PAGE_SIZE && is_array($searchAfter));

            return array_values(array_unique($ids));
        } catch (Throwable) {
            return null;
        }
    }
}
