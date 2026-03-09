<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserRelationship as Entity;
use App\User\Domain\Enum\UserRelationshipStatus;
use App\User\Domain\Repository\Interfaces\UserRelationshipRepositoryInterface;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @package App\User
 *
 * @psalm-suppress LessSpecificImplementedReturnType
 * @codingStandardsIgnoreStart
 *
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity|null findAdvanced(string $id, string|int|null $hydrationMode = null, string|null $entityManagerName = null)
 * @method Entity|null findOneBy(array $criteria, ?array $orderBy = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 * @method Entity[] findByAdvanced(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?array $search = null, ?string $entityManagerName = null)
 * @method Entity[] findAll(?string $entityManagerName = null)
 *
 * @codingStandardsIgnoreEnd
 */
class UserRelationshipRepository extends BaseRepository implements UserRelationshipRepositoryInterface
{
    /**
     * @psalm-var class-string
     */
    protected static string $entityName = Entity::class;

    /**
     * @var array<int, string>
     */
    protected static array $searchColumns = ['status'];

    public function __construct(
        protected ManagerRegistry $managerRegistry,
    ) {
    }

    public function findRelationBetweenUsers(User $firstUser, User $secondUser): ?Entity
    {
        return $this
            ->createQueryBuilder('ur')
            ->where('(ur.requester = :firstUser AND ur.addressee = :secondUser) OR (ur.requester = :secondUser AND ur.addressee = :firstUser)')
            ->setParameter('firstUser', $firstUser)
            ->setParameter('secondUser', $secondUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findIncomingRequests(User $user): array
    {
        return $this
            ->createQueryBuilder('ur')
            ->where('ur.addressee = :user')
            ->andWhere('ur.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', UserRelationshipStatus::PENDING)
            ->orderBy('ur.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findOutgoingRequests(User $user): array
    {
        return $this
            ->createQueryBuilder('ur')
            ->where('ur.requester = :user')
            ->andWhere('ur.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', UserRelationshipStatus::PENDING)
            ->orderBy('ur.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function hasActiveBlock(User $firstUser, User $secondUser): bool
    {
        $result = $this
            ->createQueryBuilder('ur')
            ->select('ur.id')
            ->where('(ur.requester = :firstUser AND ur.addressee = :secondUser) OR (ur.requester = :secondUser AND ur.addressee = :firstUser)')
            ->andWhere('ur.status = :status')
            ->setParameter('firstUser', $firstUser)
            ->setParameter('secondUser', $secondUser)
            ->setParameter('status', UserRelationshipStatus::BLOCKED)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }
}
