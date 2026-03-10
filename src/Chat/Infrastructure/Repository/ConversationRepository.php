<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation as Entity;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
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
class ConversationRepository extends BaseRepository implements ConversationRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByChat(Chat $chat): ?Entity
    {
        /** @var Entity|null $conversation */
        $conversation = $this->findOneBy([
            'chat' => $chat,
        ]);

        return $conversation;
    }

    public function findByUser(User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters(
            $this->createQueryBuilder('conversation')
                ->addSelect('chat')
                ->innerJoin('conversation.chat', 'chat')
                ->leftJoin('conversation.messages', 'messages'),
            $filters,
            $esIds
        )
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('participant.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user, array $filters = [], ?array $esIds = null): int
    {
        return (int)$this->applyListFilters($this->getConversationCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('participant.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByChatId(string $chatId, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->getConversationQueryBuilder(), $filters, $esIds)
            ->andWhere('chat.id = :chatId')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByChatId(string $chatId, array $filters = [], ?array $esIds = null): int
    {
        return (int)$this->applyListFilters($this->getConversationCountQueryBuilder(), $filters, $esIds)
            ->andWhere('chat.id = :chatId')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findByChatIdAndUser(string $chatId, User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        return $this->applyListFilters($this->getConversationQueryBuilder(), $filters, $esIds)
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('chat.id = :chatId')
            ->andWhere('participant.user = :user')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByChatIdAndUser(string $chatId, User $user, array $filters = [], ?array $esIds = null): int
    {
        return (int)$this->applyListFilters($this->getConversationCountQueryBuilder(), $filters, $esIds)
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('chat.id = :chatId')
            ->andWhere('participant.user = :user')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findDirectConversationBetweenUsers(User $firstUser, User $secondUser): ?Entity
    {
        $conversation = $this->createQueryBuilder('conversation')
            ->addSelect('participants', 'participantUser', 'messages', 'sender', 'reactions', 'reactionUser', 'chat')
            ->innerJoin('conversation.chat', 'chat')
            ->innerJoin('conversation.participants', 'participants')
            ->innerJoin('participants.user', 'participantUser')
            ->leftJoin('conversation.messages', 'messages')
            ->leftJoin('messages.sender', 'sender')
            ->leftJoin('messages.reactions', 'reactions')
            ->leftJoin('reactions.user', 'reactionUser')
            ->where('participantUser IN (:users)')
            ->setParameter('users', [$firstUser, $secondUser])
            ->groupBy('conversation.id')
            ->having('COUNT(DISTINCT participantUser.id) = 2')
            ->andHaving('COUNT(DISTINCT participants.id) = 2')
            ->orderBy('conversation.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $conversation instanceof Entity ? $conversation : null;
    }

    private function getConversationQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('conversation')
            ->addSelect('chat', 'participants', 'participantUser', 'messages', 'sender', 'reactions', 'reactionUser')
            ->innerJoin('conversation.chat', 'chat')
            ->leftJoin('conversation.participants', 'participants')
            ->leftJoin('participants.user', 'participantUser')
            ->leftJoin('conversation.messages', 'messages')
            ->leftJoin('messages.sender', 'sender')
            ->leftJoin('messages.reactions', 'reactions')
            ->leftJoin('reactions.user', 'reactionUser')
            ->distinct();
    }

    private function getConversationCountQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('conversation')
            ->select('COUNT(DISTINCT conversation.id)')
            ->innerJoin('conversation.chat', 'chat')
            ->leftJoin('conversation.messages', 'messages');
    }

    private function applyListFilters(QueryBuilder $queryBuilder, array $filters, ?array $esIds): QueryBuilder
    {
        if ($esIds !== null) {
            return $queryBuilder
                ->andWhere('conversation.id IN (:esIds)')
                ->setParameter('esIds', $esIds);
        }

        if (($filters['message'] ?? '') !== '') {
            $queryBuilder
                ->andWhere('LOWER(messages.content) LIKE LOWER(:message)')
                ->setParameter('message', '%' . $filters['message'] . '%');
        }

        return $queryBuilder;
    }
}
