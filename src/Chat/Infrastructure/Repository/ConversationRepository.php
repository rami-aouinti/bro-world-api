<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation as Entity;
use App\Chat\Domain\Enum\ConversationType;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_map;
use function array_values;
use function implode;
use function max;
use function sprintf;

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

    /**
     * @return list<Entity>
     */
    public function findByUser(User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        $ids = $this->findConversationIdsByUser($user, $filters, $offset, $limit, $esIds);

        return $this->findConversationsWithRelationsByIds($ids);
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

    /**
     * @return list<Entity>
     */
    public function findByChatId(string $chatId, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        $ids = $this->findConversationIdsByChatId($chatId, $filters, $offset, $limit, $esIds);

        return $this->findConversationsWithRelationsByIds($ids);
    }

    public function countByChatId(string $chatId, array $filters = [], ?array $esIds = null): int
    {
        return (int)$this->applyListFilters($this->getConversationCountQueryBuilder(), $filters, $esIds)
            ->andWhere('chat.id = :chatId')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<Entity>
     */
    public function findByChatIdAndUser(string $chatId, User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array
    {
        $offset = max(0, ($page - 1) * $limit);

        $ids = $this->findConversationIdsByChatIdAndUser($chatId, $user, $filters, $offset, $limit, $esIds);

        return $this->findConversationsWithRelationsByIds($ids);
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
            ->leftJoin('conversation.messages', 'messages', 'WITH', 'messages.deletedAt IS NULL')
            ->leftJoin('messages.sender', 'sender')
            ->leftJoin('messages.reactions', 'reactions')
            ->leftJoin('reactions.user', 'reactionUser')
            ->where('participantUser IN (:users)')
            ->andWhere('conversation.type = :conversationType')
            ->setParameter('users', [$firstUser, $secondUser])
            ->setParameter('conversationType', ConversationType::DIRECT)
            ->groupBy('conversation.id')
            ->having('COUNT(DISTINCT participantUser.id) = 2')
            ->andHaving('COUNT(DISTINCT participants.id) = 2')
            ->andWhere('conversation.archivedAt IS NULL')
            ->orderBy('conversation.lastMessageAt', 'DESC')
            ->addOrderBy('conversation.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $conversation instanceof Entity ? $conversation : null;
    }

    /**
     * @return list<string>
     */
    private function findConversationIdsByUser(User $user, array $filters, int $offset, int $limit, ?array $esIds): array
    {
        $qb = $this->applyListFilters($this->getConversationListIdsQueryBuilder(), $filters, $esIds)
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('participant.user = :user')
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.lastMessageAt', 'DESC')
            ->addOrderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var list<array{id:string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string)$row['id'],
            $rows
        ));
    }

    /**
     * @return list<string>
     */
    private function findConversationIdsByChatId(string $chatId, array $filters, int $offset, int $limit, ?array $esIds): array
    {
        $qb = $this->applyListFilters($this->getConversationListIdsQueryBuilder(), $filters, $esIds)
            ->andWhere('chat.id = :chatId')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.lastMessageAt', 'DESC')
            ->addOrderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var list<array{id:string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string)$row['id'],
            $rows
        ));
    }

    /**
     * @return list<string>
     */
    private function findConversationIdsByChatIdAndUser(string $chatId, User $user, array $filters, int $offset, int $limit, ?array $esIds): array
    {
        $qb = $this->applyListFilters($this->getConversationListIdsQueryBuilder(), $filters, $esIds)
            ->innerJoin('conversation.participants', 'participant')
            ->andWhere('chat.id = :chatId')
            ->andWhere('participant.user = :user')
            ->setParameter('chatId', $chatId, UuidBinaryOrderedTimeType::NAME)
            ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('conversation.lastMessageAt', 'DESC')
            ->addOrderBy('conversation.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        /** @var list<array{id:string}> $rows */
        $rows = $qb->getQuery()->getArrayResult();

        return array_values(array_map(
            static fn (array $row): string => (string)$row['id'],
            $rows
        ));
    }

    private function getConversationQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('conversation')
            ->addSelect('chat', 'participants', 'participantUser', 'messages', 'sender', 'reactions', 'reactionUser')
            ->innerJoin('conversation.chat', 'chat')
            ->leftJoin('conversation.participants', 'participants')
            ->leftJoin('participants.user', 'participantUser')
            ->leftJoin('conversation.messages', 'messages', 'WITH', 'messages.deletedAt IS NULL')
            ->leftJoin('messages.sender', 'sender')
            ->leftJoin('messages.reactions', 'reactions')
            ->leftJoin('reactions.user', 'reactionUser')
            ->andWhere('conversation.archivedAt IS NULL')
            ->distinct();
    }

    private function getConversationListIdsQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('conversation')
            ->select('conversation.id AS id')
            ->innerJoin('conversation.chat', 'chat')
            ->andWhere('conversation.archivedAt IS NULL');
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Entity>
     */
    private function findConversationsWithRelationsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('conversation')
            ->addSelect('chat')
            ->innerJoin('conversation.chat', 'chat');

        $this->applyBinaryUuidIdsFilter($qb, 'conversation.id', $ids, 'conversation_id_');

        /** @var list<Entity> $conversations */
        $conversations = $qb->getQuery()->getResult();

        $byId = [];
        foreach ($conversations as $conversation) {
            $byId[$conversation->getId()] = $conversation;
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }

        return $ordered;
    }

    private function getConversationCountQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('conversation')
            ->select('COUNT(DISTINCT conversation.id)')
            ->innerJoin('conversation.chat', 'chat')
            ->andWhere('conversation.archivedAt IS NULL');
    }

    private function applyListFilters(QueryBuilder $queryBuilder, array $filters, ?array $esIds): QueryBuilder
    {
        $this->applyBinaryUuidIdsFilter($queryBuilder, 'conversation.id', $esIds, 'conversation_filter_id_');

        if (($filters['message'] ?? '') !== '') {
            $queryBuilder
                ->andWhere(sprintf(
                    'EXISTS (SELECT 1 FROM %s filterMessage WHERE filterMessage.conversation = conversation AND filterMessage.deletedAt IS NULL AND LOWER(filterMessage.content) LIKE LOWER(:message))',
                    ChatMessage::class
                ))
                ->setParameter('message', '%' . $filters['message'] . '%');
        }

        return $queryBuilder;
    }

    /**
     * @param list<string>|null $ids
     */
    private function applyBinaryUuidIdsFilter(
        QueryBuilder $qb,
        string $field,
        ?array $ids,
        string $parameterPrefix,
    ): void {
        if ($ids === null) {
            return;
        }

        if ($ids === []) {
            $qb->andWhere('1 = 0');

            return;
        }

        $parts = [];

        foreach (array_values($ids) as $index => $id) {
            $parameterName = $parameterPrefix . $index;
            $parts[] = $field . ' = :' . $parameterName;
            $qb->setParameter($parameterName, $id, UuidBinaryOrderedTimeType::NAME);
        }

        $qb->andWhere('(' . implode(' OR ', $parts) . ')');
    }
}
