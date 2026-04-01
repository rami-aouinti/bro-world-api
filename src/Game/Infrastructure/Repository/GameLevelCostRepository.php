<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\Repository;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameLevelCost as Entity;
use App\Game\Domain\Enum\UserGameLevel;
use App\General\Infrastructure\Repository\BaseRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 */
final class GameLevelCostRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    public function findOneByGameAndLevel(Game $game, UserGameLevel $level): ?Entity
    {
        /** @var Entity|null $entity */
        $entity = $this->findOneBy([
            'game' => $game,
            'levelKey' => $level,
        ]);

        return $entity;
    }
}
