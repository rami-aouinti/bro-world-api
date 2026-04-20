<?php

declare(strict_types=1);

namespace App\Calendar\Infrastructure\Repository;

use App\Calendar\Domain\Entity\Event as Entity;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
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

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }


    public function findOneByGoogleEventIdAndUserId(string $googleEventId, string $userId): ?Entity
    {
        /** @var Entity|null $event */
        $event = $this->createQueryBuilder('event')
            ->leftJoin('event.calendar', 'calendar')
            ->andWhere('event.googleEventId = :googleEventId')
            ->andWhere('event.user = :userId OR calendar.user = :userId')
            ->setParameter('googleEventId', $googleEventId)
            ->setParameter('userId', $userId, UuidBinaryOrderedTimeType::NAME)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $event;
    }

    public function findByUser(
        User $user,
        array $filters = [],
        int $page = 1,
        int $limit = 20,
        ?array $esIds = null,
        ?DateTimeImmutable $startAtFrom = null,
        ?DateTimeImmutable $startAtTo = null,
    ): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyTimeWindow(
            $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds),
            $startAtFrom,
            $startAtTo,
        )
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('event.startAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(
        User $user,
        array $filters = [],
        ?array $esIds = null,
        ?DateTimeImmutable $startAtFrom = null,
        ?DateTimeImmutable $startAtTo = null,
    ): int
    {
        return (int)$this->applyTimeWindow(
            $this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds),
            $startAtFrom,
            $startAtTo,
        )
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByApplicationSlug(string $applicationSlug, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds)
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.visibility = :visibilityPublic')
            ->andWhere('event.isCancelled = false')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('visibilityPublic', EventVisibility::PUBLIC->value)
            ->orderBy('event.startAt', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByApplicationSlug(string $applicationSlug, array $filters = [], ?array $esIds = null): int
    {
        return (int)$this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.visibility = :visibilityPublic')
            ->andWhere('event.isCancelled = false')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('visibilityPublic', EventVisibility::PUBLIC->value)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->createBaseQueryBuilder(), $filters, $esIds)
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
        return (int)$this->applyListFilters($this->createCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('calendar.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findUpcomingPrivateByUser(User $user, int $limit = 3): array
    {
        return $this->createBaseQueryBuilder()
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->andWhere('event.visibility = :visibilityPrivate')
            ->andWhere('event.isCancelled = false')
            ->andWhere('event.startAt >= :now')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('visibilityPrivate', EventVisibility::PRIVATE->value)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('event.startAt', 'ASC')
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function findUpcomingByApplicationSlugAndUser(string $applicationSlug, User $user, int $limit = 3): array
    {
        return $this->createBaseQueryBuilder()
            ->andWhere('application.slug = :applicationSlug')
            ->andWhere('event.user = :user OR calendar.user = :user')
            ->andWhere('event.isCancelled = false')
            ->andWhere('event.startAt >= :now')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('now', new DateTimeImmutable())
            ->orderBy('event.startAt', 'ASC')
            ->setMaxResults(max(1, min(20, $limit)))
            ->getQuery()
            ->getResult();
    }

    private function createBaseQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('event')
            ->addSelect('calendar')
            ->addSelect('application')
            ->leftJoin('event.calendar', 'calendar')
            ->leftJoin('calendar.application', 'application')
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

    private function applyTimeWindow(
        QueryBuilder $queryBuilder,
        ?DateTimeImmutable $startAtFrom,
        ?DateTimeImmutable $startAtTo,
    ): QueryBuilder {
        if ($startAtFrom instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('event.startAt >= :startAtFrom')
                ->setParameter('startAtFrom', $startAtFrom);
        }

        if ($startAtTo instanceof DateTimeImmutable) {
            $queryBuilder
                ->andWhere('event.startAt <= :startAtTo')
                ->setParameter('startAtTo', $startAtTo);
        }

        return $queryBuilder;
    }
}
