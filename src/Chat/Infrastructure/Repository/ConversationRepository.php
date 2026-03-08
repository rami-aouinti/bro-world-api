<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation as Entity;
use App\Chat\Domain\Repository\Interfaces\ConversationRepositoryInterface;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ConversationRepository extends BaseRepository implements ConversationRepositoryInterface
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
        'applicationSlug',
    ];

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findOneByChatAndApplicationSlug(Chat $chat, string $applicationSlug): ?Entity
    {
        /** @var Entity|null $conversation */
        $conversation = $this->findOneBy([
            'chat' => $chat,
            'applicationSlug' => $applicationSlug,
        ]);

        return $conversation;
    }
}
