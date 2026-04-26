<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Company as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] findAll(string $entityManagerName = null)
 * @method Entity[] search(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class CompanyRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findById(string $id): ?Entity
    {
        $entity = $this->createQueryBuilder('company')
            ->andWhere('company.id = :id')
            ->setParameter('id', $id, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $entity = $this->createQueryBuilder('company')
            ->andWhere('company.id = :id')
            ->andWhere('company.crm = :crmId')
            ->setParameter('id', $id)
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
        return $this->createQueryBuilder('company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('company.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function countCompaniesByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('company')
            ->select('COUNT(company.id)')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{q?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('company')
            ->select('company.id, company.name, company.industry, company.website, company.contactEmail, company.phone')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('company.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(company.name) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param string $crmId
     * @param array{q?:string} $filters
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('company')
            ->select('COUNT(company.id)')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(company.name) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
