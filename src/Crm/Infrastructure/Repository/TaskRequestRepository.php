<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\TaskRequest as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_map;
use function array_values;
use function implode;
use function trim;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class TaskRequestRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $qb = $this->createScopedBaseQb($crmId, true)
            ->andWhere('taskRequest.id = :id')
            ->setParameter('id', $id, UuidBinaryOrderedTimeType::NAME)
            ->setMaxResults(1);

        $entity = $qb->getQuery()->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        /** @var list<Entity> $items */
        $items = $this->createScopedBaseQb($crmId)
            ->orderBy('taskRequest.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $items;
    }

    public function countTaskRequestsByCrm(string $crmId): int
    {
        return (int)$this->createScopedCountQb($crmId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTaskRequestsByCrmAndStatus(string $crmId, string $status): int
    {
        return (int)$this->createScopedCountQb($crmId)
            ->andWhere('taskRequest.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByGithubIssueMapping(string $repositoryFullName, int $issueNumber): ?Entity
    {
        $entity = $this->createQueryBuilder('taskRequest')
            ->leftJoin('taskRequest.githubIssue', 'githubIssue')
            ->addSelect('githubIssue')
            ->andWhere('githubIssue.repositoryFullName = :repositoryFullName')
            ->andWhere('githubIssue.issueNumber = :issueNumber')
            ->setParameter('repositoryFullName', trim($repositoryFullName))
            ->setParameter('issueNumber', $issueNumber)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findAllWithGithubIssueMapping(): array
    {
        /** @var list<Entity> $entities */
        $entities = $this->createQueryBuilder('taskRequest')
            ->leftJoin('taskRequest.githubIssue', 'githubIssue')
            ->addSelect('githubIssue', 'task', 'project')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->andWhere('githubIssue.issueNumber IS NOT NULL')
            ->andWhere('githubIssue.repositoryFullName <> :emptyName')
            ->setParameter('emptyName', '')
            ->getQuery()
            ->getResult();

        return $entities;
    }

    /**
     * @param array{q?:string,status?:string,ids?:list<string>|null} $filters
     *
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $idsQb = $this->createQueryBuilder('taskRequest')
            ->select('taskRequest.id AS id')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('taskRequest.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $this->applyProjectionFilters($idsQb, $filters);

        /** @var list<array{id:string}> $idRows */
        $idRows = $idsQb->getQuery()->getArrayResult();

        $taskRequestIds = array_values(array_map(
            static fn (array $row): string => (string)$row['id'],
            $idRows
        ));

        if ($taskRequestIds === []) {
            return [];
        }

        $itemsQb = $this->createQueryBuilder('taskRequest')
            ->select('taskRequest.id, taskRequest.title, taskRequest.status, taskRequest.requestedAt, taskRequest.resolvedAt, IDENTITY(taskRequest.task) AS taskId, IDENTITY(taskRequest.repository) AS repositoryId')
            ->addSelect('githubIssue.provider AS githubIssueProvider, githubIssue.repositoryFullName AS githubIssueRepositoryFullName, githubIssue.issueNumber AS githubIssueIssueNumber, githubIssue.issueNodeId AS githubIssueIssueNodeId, githubIssue.issueUrl AS githubIssueIssueUrl, githubIssue.syncStatus AS githubIssueSyncStatus, githubIssue.lastSyncedAt AS githubIssueLastSyncedAt')
            ->leftJoin('taskRequest.githubIssue', 'githubIssue');

        $this->applyBinaryUuidIdsFilter($itemsQb, 'taskRequest.id', $taskRequestIds, 'task_request_id_');

        /** @var list<array<string,mixed>> $items */
        $items = $itemsQb->getQuery()->getArrayResult();

        if ($items === []) {
            return [];
        }

        $assigneeQb = $this->createQueryBuilder('taskRequest')
            ->select('taskRequest.id AS taskRequestId, assignee.id, assignee.username, assignee.firstName, assignee.lastName, assignee.photo')
            ->leftJoin('taskRequest.assignees', 'assignee')
            ->andWhere('assignee.id IS NOT NULL');

        $this->applyBinaryUuidIdsFilter($assigneeQb, 'taskRequest.id', $taskRequestIds, 'assignee_task_request_id_');

        /** @var list<array<string,mixed>> $assigneeRows */
        $assigneeRows = $assigneeQb->getQuery()->getArrayResult();

        $assigneesByTaskRequest = [];
        foreach ($assigneeRows as $row) {
            $taskRequestId = (string)($row['taskRequestId'] ?? '');
            if ($taskRequestId === '') {
                continue;
            }

            $assigneesByTaskRequest[$taskRequestId][] = [
                'id' => $row['id'] ?? null,
                'username' => $row['username'] ?? null,
                'firstName' => $row['firstName'] ?? null,
                'lastName' => $row['lastName'] ?? null,
                'photo' => $row['photo'] ?? null,
            ];
        }

        $branchQb = $this->createQueryBuilder('taskRequest')
            ->select('taskRequest.id AS taskRequestId, githubBranch.id, githubBranch.repositoryFullName, githubBranch.branchName, githubBranch.branchSha, githubBranch.branchUrl, githubBranch.issueNumber, githubBranch.syncStatus, githubBranch.createdAt, githubBranch.lastSyncedAt, githubBranch.metadata')
            ->leftJoin('taskRequest.githubBranches', 'githubBranch')
            ->andWhere('githubBranch.id IS NOT NULL');

        $this->applyBinaryUuidIdsFilter($branchQb, 'taskRequest.id', $taskRequestIds, 'branch_task_request_id_');

        /** @var list<array<string,mixed>> $branchRows */
        $branchRows = $branchQb->getQuery()->getArrayResult();

        $branchesByTaskRequest = [];
        foreach ($branchRows as $row) {
            $taskRequestId = (string)($row['taskRequestId'] ?? '');
            if ($taskRequestId === '') {
                continue;
            }

            $branchesByTaskRequest[$taskRequestId][] = [
                'id' => $row['id'] ?? null,
                'repositoryFullName' => $row['repositoryFullName'] ?? null,
                'branchName' => $row['branchName'] ?? null,
                'branchSha' => $row['branchSha'] ?? null,
                'branchUrl' => $row['branchUrl'] ?? null,
                'issueNumber' => $row['issueNumber'] ?? null,
                'syncStatus' => $row['syncStatus'] ?? null,
                'createdAt' => $row['createdAt'] ?? null,
                'lastSyncedAt' => $row['lastSyncedAt'] ?? null,
                'metadata' => $row['metadata'] ?? [],
            ];
        }

        $itemsById = [];
        foreach ($items as $item) {
            $id = (string)($item['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $item['assignees'] = $assigneesByTaskRequest[$id] ?? [];
            $item['githubBranches'] = $branchesByTaskRequest[$id] ?? [];
            $itemsById[$id] = $item;
        }

        $orderedItems = [];
        foreach ($taskRequestIds as $id) {
            if (isset($itemsById[$id])) {
                $orderedItems[] = $itemsById[$id];
            }
        }

        return $orderedItems;
    }

    /**
     * @param array{q?:string,status?:string,ids?:list<string>|null} $filters
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createScopedCountQb($crmId);

        $this->applyProjectionFilters($qb, $filters);

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    private function createScopedBaseQb(string $crmId, bool $includeBlogDetails = false): QueryBuilder
    {
        $qb = $this->createQueryBuilder('taskRequest')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $qb->addSelect('githubIssue', 'githubBranch')
            ->leftJoin('taskRequest.githubIssue', 'githubIssue')
            ->leftJoin('taskRequest.githubBranches', 'githubBranch');

        if ($includeBlogDetails) {
            $qb->addSelect('blog', 'post', 'postComment', 'postReaction', 'commentReaction')
                ->leftJoin('taskRequest.blog', 'blog')
                ->leftJoin('blog.posts', 'post')
                ->leftJoin('post.comments', 'postComment')
                ->leftJoin('post.reactions', 'postReaction')
                ->leftJoin('postComment.reactions', 'commentReaction');
        }

        return $qb;
    }

    private function createScopedCountQb(string $crmId): QueryBuilder
    {
        return $this->createQueryBuilder('taskRequest')
            ->select('COUNT(taskRequest.id)')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);
    }

    /**
     * @param array{q?:string,status?:string,ids?:list<string>|null} $filters
     */
    private function applyProjectionFilters(QueryBuilder $qb, array $filters): void
    {
        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(taskRequest.title) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('taskRequest.status = :status')
                ->setParameter('status', $status);
        }

        $this->applyBinaryUuidIdsFilter($qb, 'taskRequest.id', $filters['ids'] ?? null, 'projection_task_request_id_');
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

        $parts = [];

        foreach (array_values($ids) as $index => $id) {
            $parameterName = $parameterPrefix . $index;
            $parts[] = $field . ' = :' . $parameterName;
            $qb->setParameter($parameterName, $id, UuidBinaryOrderedTimeType::NAME);
        }

        $qb->andWhere('(' . implode(' OR ', $parts) . ')');
    }
}
