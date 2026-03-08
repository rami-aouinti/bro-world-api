<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant as Entity;
use App\Chat\Domain\Repository\Interfaces\ConversationParticipantRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ConversationParticipantRepository extends BaseRepository implements ConversationParticipantRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findOneByConversationAndUser(Conversation $conversation, User $user): ?Entity
    {
        $conversationParticipant = $this->findOneBy([
            'conversation' => $conversation,
            'user' => $user,
        ]);

        return $conversationParticipant instanceof Entity ? $conversationParticipant : null;
    }
}
