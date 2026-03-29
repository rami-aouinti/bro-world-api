<?php

declare(strict_types=1);

namespace App\Game\Domain\Entity;

use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'game_definition')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Game implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: GameCategory::class, inversedBy: 'games')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?GameCategory $category = null;

    /**
     * @var Collection<int, GameSession>
     */
    #[ORM\OneToMany(targetEntity: GameSession::class, mappedBy: 'game', cascade: ['remove'])]
    private Collection $sessions;

    /**
     * @var Collection<int, GameStatistic>
     */
    #[ORM\OneToMany(targetEntity: GameStatistic::class, mappedBy: 'game', cascade: ['remove'])]
    private Collection $statistics;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'metadata', type: Types::JSON)]
    private array $metadata = [];

    #[ORM\Column(name: 'level', type: Types::STRING, length: 25, enumType: GameLevel::class)]
    private GameLevel $level = GameLevel::BEGINNER;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: GameStatus::class)]
    private GameStatus $status = GameStatus::ACTIVE;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->sessions = new ArrayCollection();
        $this->statistics = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getCategory(): ?GameCategory
    {
        return $this->category;
    }

    public function setCategory(?GameCategory $category): self
    {
        $this->category = $category;

        return $this;
    }

    /**
     * @return Collection<int, GameSession>
     */
    public function getSessions(): Collection
    {
        return $this->sessions;
    }

    /**
     * @return Collection<int, GameStatistic>
     */
    public function getStatistics(): Collection
    {
        return $this->statistics;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getLevel(): GameLevel
    {
        return $this->level;
    }

    public function setLevel(GameLevel $level): self
    {
        $this->level = $level;

        return $this;
    }

    public function getStatus(): GameStatus
    {
        return $this->status;
    }

    public function setStatus(GameStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
}
