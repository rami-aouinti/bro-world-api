<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\ChatMessageReaction as Entity;
use App\Chat\Domain\Enum\ChatReactionType;
use App\Chat\Domain\Repository\Interfaces\ChatMessageReactionRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ChatMessageReactionRepository extends BaseRepository implements ChatMessageReactionRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = ['id', 'reaction'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByMessageUserReaction(ChatMessage $message, User $user, ChatReactionType $reaction): ?Entity
    {
        /** @var Entity|null $entity */
        $entity = $this->findOneBy([
            'message' => $message,
            'user' => $user,
            'reaction' => $reaction,
        ]);

        return $entity;
    }
}
