<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Chat as Entity;
use App\Chat\Domain\Repository\Interfaces\ChatRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ChatRepository extends BaseRepository implements ChatRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'applicationSlug',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByApplication(Application $application): ?Entity
    {
        $chat = $this->findOneBy([
            'application' => $application,
        ]);

        return $chat instanceof Entity ? $chat : null;
    }

    public function findChatForDirectConversation(User $actor, User $targetUser): ?Entity
    {
        $sharedConversationChat = $this->createQueryBuilder('chat')
            ->innerJoin('chat.conversations', 'conversation')
            ->innerJoin('conversation.participants', 'participant')
            ->innerJoin('participant.user', 'participantUser')
            ->where('participantUser.id IN (:users)')
            ->setParameter('users', [$actor->getId(), $targetUser->getId()], UuidBinaryOrderedTimeType::NAME)
            ->groupBy('chat.id')
            ->having('COUNT(DISTINCT participantUser.id) = 2')
            ->orderBy('MAX(conversation.createdAt)', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($sharedConversationChat instanceof Entity) {
            return $sharedConversationChat;
        }

        $actorOwnedChat = $this->createQueryBuilder('chat')
            ->innerJoin('chat.application', 'application')
            ->innerJoin('application.user', 'applicationOwner')
            ->where('applicationOwner.id = :actorId')
            ->setParameter('actorId', $actor->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('chat.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($actorOwnedChat instanceof Entity) {
            return $actorOwnedChat;
        }

        $targetOwnedChat = $this->createQueryBuilder('chat')
            ->innerJoin('chat.application', 'application')
            ->innerJoin('application.user', 'applicationOwner')
            ->where('applicationOwner.id = :targetId')
            ->setParameter('targetId', $targetUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('chat.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $targetOwnedChat instanceof Entity ? $targetOwnedChat : null;
    }
}
