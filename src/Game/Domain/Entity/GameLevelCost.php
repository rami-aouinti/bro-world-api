<?php

declare(strict_types=1);

namespace App\Game\Domain\Entity;

use App\Game\Domain\Enum\UserGameLevel;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'game_level_cost', uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_game_level_cost_game_level', columns: ['game_id', 'level_key']),
])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class GameLevelCost implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game $game = null;

    #[ORM\Column(name: 'level_key', type: Types::STRING, length: 16, enumType: UserGameLevel::class)]
    private UserGameLevel $levelKey = UserGameLevel::EASY;

    #[ORM\Column(name: 'min_coins_cost', type: Types::BIGINT)]
    private int $minCoinsCost = 0;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(?Game $game): self
    {
        $this->game = $game;

        return $this;
    }

    public function getLevelKey(): UserGameLevel
    {
        return $this->levelKey;
    }

    public function setLevelKey(UserGameLevel $levelKey): self
    {
        $this->levelKey = $levelKey;

        return $this;
    }

    public function getMinCoinsCost(): int
    {
        return $this->minCoinsCost;
    }

    public function setMinCoinsCost(int $minCoinsCost): self
    {
        $this->minCoinsCost = $minCoinsCost;

        return $this;
    }
}
