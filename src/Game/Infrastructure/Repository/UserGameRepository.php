<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\Repository;

use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Entity\UserGame as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 */
final class UserGameRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findOneByUserAndIdempotencyKey(User $user, string $idempotencyKey): ?Entity
    {
        /** @var Entity|null $entity */
        $entity = $this->findOneBy([
            'user' => $user,
            'idempotencyKey' => $idempotencyKey,
        ]);

        return $entity;
    }

    public function findOneBySession(GameSession $session): ?Entity
    {
        /** @var Entity|null $entity */
        $entity = $this->findOneBy([
            'session' => $session,
        ]);

        return $entity;
    }
}
