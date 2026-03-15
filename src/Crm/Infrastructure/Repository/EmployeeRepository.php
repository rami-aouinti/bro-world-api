<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Employee as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class EmployeeRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $entity = $this->createQueryBuilder('employee')
            ->andWhere('employee.id = :id')
            ->andWhere('employee.crm = :crmId')
            ->setParameter('id', $id)
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        return $this->createQueryBuilder('employee')
            ->andWhere('employee.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('employee.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function countByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('employee')
            ->select('COUNT(employee.id)')
            ->andWhere('employee.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()->getSingleScalarResult();
    }
}
