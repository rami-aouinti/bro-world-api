<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Project as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] count(array $criteria, ?string $entityManagerName = null)
 */
class ProjectRepository extends BaseRepository
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
        $entity = $this->createQueryBuilder('project')
            ->leftJoin('project.company', 'company')
            ->andWhere('project.id = :id')
            ->andWhere('company.crm = :crmId')
            ->setParameter('id', $id)
            ->setParameter('crmId', $crmId)
            ->getQuery()
            ->getOneOrNullResult();

        return $entity instanceof Entity ? $entity : null;
    }

    /** @return list<Entity> */
    public function findScoped(string $crmId, int $limit = 200, int $offset = 0): array
    {
        return $this->createQueryBuilder('project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->orderBy('project.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }


    public function countProjectsByCrm(string $crmId): int
    {
        return (int)$this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param array{q?:string,status?:string} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('project')
            ->select('project.id, project.name, project.status, IDENTITY(project.company) AS companyId')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId)
            ->orderBy('project.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(project.name) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('project.status = :status')->setParameter('status', $status);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /** @param array{q?:string,status?:string} $filters */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('project')
            ->select('COUNT(project.id)')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->setParameter('crmId', $crmId);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(project.name) LIKE LOWER(:q)')->setParameter('q', '%' . $query . '%');
        }

        $status = trim((string)($filters['status'] ?? ''));
        if ($status !== '') {
            $qb->andWhere('project.status = :status')->setParameter('status', $status);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

}
