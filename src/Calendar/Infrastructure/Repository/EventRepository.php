<?php

declare(strict_types=1);

namespace App\Calendar\Infrastructure\Repository;

use App\Calendar\Domain\Entity\Event as Entity;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class EventRepository extends BaseRepository implements EventRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'title',
        'description',
        'status',
        'visibility',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findByUser(User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds)
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('event.startAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user, array $filters = [], ?array $esIds = null): int
    {
        return (int) $this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds)
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByApplicationSlug(string $applicationSlug, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->orderBy('event.startAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByApplicationSlug(string $applicationSlug, array $filters = [], ?array $esIds = null): int
    {
        return (int) $this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('event.startAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], ?array $esIds = null): int
    {
        return (int) $this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('event')
            ->addSelect('calendar')
            ->leftJoin('event.calendar', 'calendar')
            ->distinct();
    }

    private function createCountQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('event')
            ->select('COUNT(DISTINCT event.id)')
            ->leftJoin('event.calendar', 'calendar');
    }

    private function applyListFilters(QueryBuilder $queryBuilder, array $filters, ?array $esIds): QueryBuilder
    {
        if ($esIds !== null) {
            return $queryBuilder
                ->andWhere('event.id IN (:esIds)')
                ->setParameter('esIds', $esIds);
        }

        if (($filters['title'] ?? '') !== '') {
            $queryBuilder
                ->andWhere('LOWER(event.title) LIKE LOWER(:title)')
                ->setParameter('title', '%' . $filters['title'] . '%');
        }

        if (($filters['description'] ?? '') !== '') {
            $queryBuilder
                ->andWhere('LOWER(event.description) LIKE LOWER(:description)')
                ->setParameter('description', '%' . $filters['description'] . '%');
        }

        if (($filters['location'] ?? '') !== '') {
            $queryBuilder
                ->andWhere('LOWER(event.location) LIKE LOWER(:location)')
                ->setParameter('location', '%' . $filters['location'] . '%');
        }

        return $queryBuilder;
    }
}
