<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\Repository;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession as Entity;
use App\Game\Domain\Enum\GameStatus;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
final class GameSessionRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    /**
     * @return list<Entity>
     */
    public function findCompletedByGameAndUser(Game $game, ?User $user): array
    {
        $criteria = [
            'game' => $game,
            'status' => GameStatus::COMPLETED,
        ];

        if (null !== $user) {
            $criteria['user'] = $user;
        }

        /** @var list<Entity> $sessions */
        $sessions = $this->findBy($criteria, ['startedAt' => 'ASC']);

        return $sessions;
    }
}
