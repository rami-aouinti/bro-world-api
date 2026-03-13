<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\TaskRequest as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] count(array $criteria, ?string $entityManagerName = null)
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
        $entity = $this->createQueryBuilder('taskRequest')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('taskRequest.id = :id')
            ->andWhere('company.crm = :crmId')
            ->setParameter('id', $id)
            ->setParameter('crmId', $crmId)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        return $this->createQueryBuilder('taskRequest')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->orderBy('taskRequest.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countTaskRequestsByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('taskRequest')
            ->select('COUNT(taskRequest.id)')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countTaskRequestsByCrmAndStatus(string $crmId, string $status): int
    {
        return (int)$this->createQueryBuilder('taskRequest')
            ->select('COUNT(taskRequest.id)')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->andWhere('taskRequest.status = :status')
            ->setParameter('crmId', $crmId)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{q?:string,status?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('taskRequest')
            ->select('taskRequest.id, taskRequest.title, taskRequest.status, taskRequest.requestedAt, taskRequest.resolvedAt, IDENTITY(taskRequest.task) AS taskId')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->orderBy('taskRequest.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(taskRequest.title) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('taskRequest.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param array{q?:string,status?:string} $filters
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('taskRequest')
            ->select('COUNT(taskRequest.id)')
            ->leftJoin('taskRequest.task', 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(taskRequest.title) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('taskRequest.status = :status')->setParameter('status', $status);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
