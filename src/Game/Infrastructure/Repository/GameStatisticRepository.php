<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\Repository;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameStatistic as Entity;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
final class GameStatisticRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    public function __construct(protected ManagerRegistry $managerRegistry)
    {
    }

    /**
     * @return list<Entity>
     */
    public function findByGameAndUser(Game $game, ?User $user): array
    {
        $criteria = ['game' => $game, 'user' => $user];

        /** @var list<Entity> $statistics */
        $statistics = $this->findBy($criteria, ['key' => 'ASC']);

        return $statistics;
    }

    public function replaceForGameAndUser(Game $game, ?User $user, Entity ...$statistics): void
    {
        foreach ($this->findByGameAndUser($game, $user) as $existing) {
            $this->remove($existing, false);
        }

        foreach ($statistics as $statistic) {
            $this->save($statistic, false);
        }

        $this->getEntityManager()->flush();
    }
}
