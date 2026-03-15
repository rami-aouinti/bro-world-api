<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Contact as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class ContactRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneScopedById(string $id, string $crmId): ?Entity
    {
        $entity = $this->createQueryBuilder('contact')
            ->andWhere('contact.id = :id')
            ->andWhere('contact.crm = :crmId')
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
        return $this->createQueryBuilder('contact')
            ->andWhere('contact.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('contact.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()->getResult();
    }

    public function countByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('contact')
            ->select('COUNT(contact.id)')
            ->andWhere('contact.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()->getSingleScalarResult();
    }

    /**
     * @param array{q?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('contact')
            ->select('contact.id, contact.firstName, contact.lastName, contact.email, contact.phone, contact.jobTitle, contact.city, contact.score, IDENTITY(contact.company) AS companyId')
            ->andWhere('contact.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('contact.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(CONCAT(contact.firstName, :space, contact.lastName)) LIKE LOWER(:q) OR LOWER(contact.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%')
                ->setParameter('space', ' ');
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param array{q?:string} $filters
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('contact')
            ->select('COUNT(contact.id)')
            ->andWhere('contact.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb
                ->andWhere('LOWER(CONCAT(contact.firstName, :space, contact.lastName)) LIKE LOWER(:q) OR LOWER(contact.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%')
                ->setParameter('space', ' ');
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
