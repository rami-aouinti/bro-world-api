<?php

declare(strict_types=1);

namespace App\Game\Domain\Entity;

use App\Game\Domain\Enum\UserGameLevel;
use App\Game\Domain\Enum\UserGameResult;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'user_game', indexes: [
    new ORM\Index(name: 'idx_user_game_user_created_at', columns: ['user_id', 'created_at']),
], uniqueConstraints: [
    new ORM\UniqueConstraint(name: 'uniq_user_game_user_idempotency', columns: ['user_id', 'idempotency_key']),
])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class UserGame implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(name: 'game_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Game $game = null;

    #[ORM\Column(name: 'selected_level', type: Types::STRING, length: 16, enumType: UserGameLevel::class)]
    private UserGameLevel $selectedLevel = UserGameLevel::EASY;

    #[ORM\Column(name: 'entry_cost_coins', type: Types::BIGINT)]
    private int $entryCostCoins = 0;

    #[ORM\Column(name: 'result', type: Types::STRING, length: 8, enumType: UserGameResult::class)]
    private UserGameResult $result = UserGameResult::LOSE;

    #[ORM\Column(name: 'reward_or_penalty_coins', type: Types::BIGINT)]
    private int $rewardOrPenaltyCoins = 0;

    #[ORM\Column(name: 'idempotency_key', type: Types::STRING, length: 100, nullable: true)]
    private ?string $idempotencyKey = null;

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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getSelectedLevel(): UserGameLevel
    {
        return $this->selectedLevel;
    }

    public function setSelectedLevel(UserGameLevel $selectedLevel): self
    {
        $this->selectedLevel = $selectedLevel;

        return $this;
    }

    public function getEntryCostCoins(): int
    {
        return $this->entryCostCoins;
    }

    public function setEntryCostCoins(int $entryCostCoins): self
    {
        $this->entryCostCoins = $entryCostCoins;

        return $this;
    }

    public function getResult(): UserGameResult
    {
        return $this->result;
    }

    public function setResult(UserGameResult $result): self
    {
        $this->result = $result;

        return $this;
    }

    public function getRewardOrPenaltyCoins(): int
    {
        return $this->rewardOrPenaltyCoins;
    }

    public function setRewardOrPenaltyCoins(int $rewardOrPenaltyCoins): self
    {
        $this->rewardOrPenaltyCoins = $rewardOrPenaltyCoins;

        return $this;
    }

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(?string $idempotencyKey): self
    {
        $this->idempotencyKey = $idempotencyKey;

        return $this;
    }
}
