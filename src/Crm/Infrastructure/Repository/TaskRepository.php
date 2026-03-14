<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Task as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] count(array $criteria, ?string $entityManagerName = null)
 */
class TaskRepository extends BaseRepository
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
        $entity = $this->createQueryBuilder('task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('task.id = :id')
            ->andWhere('company.crm = :crmId')
            ->setParameter('id', $id, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        return $this->createQueryBuilder('task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('task.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countTasksByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('task')
            ->select('COUNT(task.id)')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Entity>
     */
    public function findScopedBySprint(string $crmId, string $sprintId): array
    {
        /** @var list<Entity> $items */
        $items = $this->createQueryBuilder('task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->leftJoin('task.sprint', 'sprint')
            ->andWhere('company.crm = :crmId')
            ->andWhere('sprint.id = :sprintId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('sprintId', $sprintId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('task.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $items;
    }

}
