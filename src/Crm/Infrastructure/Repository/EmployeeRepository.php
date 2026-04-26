<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\Repository;

use App\Crm\Domain\Entity\Employee as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_values;
use function trim;

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

    /**
     * @return list<User>
     */
    public function findUsersByApplication(Application $application): array
    {
        /** @var list<User> $users */
        $users = $this->createQueryBuilder('employee')
            ->select('DISTINCT user')
            ->innerJoin('employee.crm', 'crm')
            ->innerJoin('employee.user', 'user')
            ->andWhere('crm.application = :application')
            ->setParameter('application', $application)
            ->getQuery()
            ->getResult();

        return $users;
    }

    public function existsByApplicationSlugAndUser(string $applicationSlug, User $user): bool
    {
        $count = (int)$this->createQueryBuilder('employee')
            ->select('COUNT(employee.id)')
            ->innerJoin('employee.crm', 'crm')
            ->innerJoin('crm.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('employee.user = :user')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param array{q?:string,ids?:list<string>|null} $filters
     * @return list<array<string,mixed>>
     */
    public function findScopedProjection(string $crmId, int $limit, int $offset, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('employee')
            ->select('employee.id, employee.firstName, employee.lastName, employee.email, user.id AS userId , user.firstName AS userFirstName , user.lastName AS userLastName , employee.positionName, employee.roleName, employee.createdAt, employee.updatedAt, user.photo AS photo')
            ->leftJoin('employee.user', 'user')
            ->andWhere('employee.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('employee.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(CONCAT(employee.firstName, :space, employee.lastName)) LIKE LOWER(:q) OR LOWER(employee.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%')
                ->setParameter('space', ' ');
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids)) {
            if ($ids === []) {
                return [];
            }

            $qb->andWhere('employee.id IN (:ids)')
                ->setParameter('ids', array_values($ids), UuidBinaryOrderedTimeType::NAME);
        }

        return $qb->getQuery()->getArrayResult();
    }

    /**
     * @param string $crmId
     * @param array{q?:string,ids?:list<string>|null} $filters
     * @return int
     * @throws NoResultException
     * @throws NonUniqueResultException
     */
    public function countScopedByCrm(string $crmId, array $filters = []): int
    {
        $qb = $this->createQueryBuilder('employee')
            ->select('COUNT(employee.id)')
            ->andWhere('employee.crm = :crmId')
            ->setParameter('crmId', $crmId, UuidBinaryOrderedTimeType::NAME);

        $query = trim((string)($filters['q'] ?? ''));
        if ($query !== '') {
            $qb->andWhere('LOWER(CONCAT(employee.firstName, :space, employee.lastName)) LIKE LOWER(:q) OR LOWER(employee.email) LIKE LOWER(:q)')
                ->setParameter('q', '%' . $query . '%')
                ->setParameter('space', ' ');
        }

        $ids = $filters['ids'] ?? null;
        if (is_array($ids)) {
            if ($ids === []) {
                return 0;
            }

            $qb->andWhere('employee.id IN (:ids)')
                ->setParameter('ids', array_values($ids), UuidBinaryOrderedTimeType::NAME);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }
}
