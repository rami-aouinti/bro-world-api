<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Notification\Domain\Entity\Notification;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Notification|null find(string $id, $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 */
class NotificationRepository extends BaseRepository
{
    protected static string $entityName = Notification::class;
    protected static array $searchColumns = ['id', 'title', 'description', 'type'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return Notification[]
     */
    public function findByRecipient(User $user, int $limit = 50, int $offset = 0): array
    {
        $result = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($result, static fn ($notification): bool => $notification instanceof Notification));
    }

    public function countUnreadByRecipient(User $user): int
    {
        return (int)$this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('recipient', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllAsReadByRecipient(User $user): int
    {
        return $this->markAllAsReadByRecipientId($user);
    }

    public function markAllAsReadByRecipientId(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->andWhere('n.recipient = :recipient')
            ->andWhere('n.isRead = :currentState')
            ->setParameter('isRead', true)
            ->setParameter('currentState', false)
            ->setParameter('recipient', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->execute();
    }
}
