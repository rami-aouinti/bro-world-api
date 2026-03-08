<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Notification\Domain\Entity\Notification;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Notification|null find(string $id, $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 */
class NotificationRepository extends BaseRepository
{
    protected static string $entityName = Notification::class;
    protected static array $searchColumns = ['id', 'title', 'description', 'type'];

    public function __construct(protected ManagerRegistry $managerRegistry) {}

    /** @return Notification[] */
    public function findByRecipient(User $user, int $limit = 50, int $offset = 0): array
    {
        $result = $this->createQueryBuilder('n')
            ->andWhere('n.recipient = :recipient')
            ->setParameter('recipient', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_values(array_filter($result, static fn ($notification): bool => $notification instanceof Notification));
    }
}
