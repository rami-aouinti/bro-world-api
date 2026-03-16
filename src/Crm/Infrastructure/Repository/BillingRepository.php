<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Billing as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function trim;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] count(array $criteria, ?string $entityManagerName = null)
 */
class BillingRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $entity = $this->createQueryBuilder('billing')
            ->leftJoin('billing.company', 'company')
            ->andWhere('billing.id = :id')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('id', $id, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /**
     * @return list<Entity>
     */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        /** @var list<Entity> $items */
        $items = $this->createQueryBuilder('billing')
            ->leftJoin('billing.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('billing.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $items;
    }

    /**
     * @param array{q?:string,status?:string,companyId?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('billing')
            ->select('billing.id, billing.label, billing.amount, billing.currency, billing.status, billing.dueAt, billing.paidAt, IDENTITY(billing.company) AS companyId')
            ->leftJoin('billing.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('billing.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(billing.label) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('billing.status = :status')
                ->setParameter('status', $status);
        }

        $companyId = trim((string)($filters['companyId'] ?? ''));
        if ($companyId !== '') {
            $qb->andWhere('IDENTITY(billing.company) = :companyId')
                ->setParameter('companyId', $companyId, UuidBinaryOrderedTimeType::NAME);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param array{q?:string,status?:string,companyId?:string} $filters
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('billing')
            ->select('COUNT(billing.id)')
            ->leftJoin('billing.company', 'company')
            ->andWhere('IDENTITY(company.crm) = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(billing.label) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('billing.status = :status')
                ->setParameter('status', $status);
        }

        $companyId = trim((string)($filters['companyId'] ?? ''));
        if ($companyId !== '') {
            $qb->andWhere('IDENTITY(billing.company) = :companyId')
                ->setParameter('companyId', $companyId, UuidBinaryOrderedTimeType::NAME);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<Entity>
     */
    public function findByCrm(string $crmId, int $limit = 200, int $offset = 0): array
    {
        return $this->findScoped($crmId, $limit, $offset);
    }
}
