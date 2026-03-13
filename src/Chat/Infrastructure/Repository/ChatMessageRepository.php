<?php

declare(strict_types=1);

namespace App\Chat\Infrastructure\Repository;

use App\Chat\Domain\Entity\ChatMessage as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ChatMessageRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = ['id', 'content'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
