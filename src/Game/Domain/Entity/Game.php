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

    #[ORM\ManyToOne(targetEntity: GameSubCategory::class, inversedBy: 'games')]
    #[ORM\JoinColumn(name: 'sub_category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?GameSubCategory $subCategory = null;

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

    #[ORM\Column(name: 'game_key', type: Types::STRING, length: 100, unique: true)]
    private string $key = '';

    #[ORM\Column(name: 'name_key', type: Types::STRING, length: 255)]
    private string $nameKey = '';

    #[ORM\Column(name: 'description_key', type: Types::STRING, length: 255, nullable: true)]
    private ?string $descriptionKey = null;

    #[ORM\Column(name: 'img', type: Types::STRING, length: 255, nullable: true)]
    private ?string $img = null;

    #[ORM\Column(name: 'icon', type: Types::STRING, length: 255, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(name: 'component', type: Types::STRING, length: 255, nullable: true)]
    private ?string $component = null;

    #[ORM\Column(name: 'supported_modes', type: Types::JSON)]
    private array $supportedModes = [];

    #[ORM\Column(name: 'category_key', type: Types::STRING, length: 100, nullable: true)]
    private ?string $categoryKey = null;

    #[ORM\Column(name: 'subcategory_key', type: Types::STRING, length: 100, nullable: true)]
    private ?string $subcategoryKey = null;

    #[ORM\Column(name: 'difficulty_key', type: Types::STRING, length: 100, nullable: true)]
    private ?string $difficultyKey = null;

    #[ORM\Column(name: 'tags', type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(name: 'features', type: Types::JSON)]
    private array $features = [];

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

    public function getSubCategory(): ?GameSubCategory
    {
        return $this->subCategory;
    }

    public function setSubCategory(?GameSubCategory $subCategory): self
    {
        $this->subCategory = $subCategory;

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

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): self
    {
        $this->key = $key;

        return $this;
    }

    public function getNameKey(): string
    {
        return $this->nameKey;
    }

    public function setNameKey(string $nameKey): self
    {
        $this->nameKey = $nameKey;

        return $this;
    }

    public function getDescriptionKey(): ?string
    {
        return $this->descriptionKey;
    }

    public function setDescriptionKey(?string $descriptionKey): self
    {
        $this->descriptionKey = $descriptionKey;

        return $this;
    }

    public function getImg(): ?string
    {
        return $this->img;
    }

    public function setImg(?string $img): self
    {
        $this->img = $img;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;

        return $this;
    }

    public function getComponent(): ?string
    {
        return $this->component;
    }

    public function setComponent(?string $component): self
    {
        $this->component = $component;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getSupportedModes(): array
    {
        return $this->supportedModes;
    }

    /**
     * @param array<int, string> $supportedModes
     */
    public function setSupportedModes(array $supportedModes): self
    {
        $this->supportedModes = $supportedModes;

        return $this;
    }

    public function getCategoryKey(): ?string
    {
        return $this->categoryKey;
    }

    public function setCategoryKey(?string $categoryKey): self
    {
        $this->categoryKey = $categoryKey;

        return $this;
    }

    public function getSubcategoryKey(): ?string
    {
        return $this->subcategoryKey;
    }

    public function setSubcategoryKey(?string $subcategoryKey): self
    {
        $this->subcategoryKey = $subcategoryKey;

        return $this;
    }

    public function getDifficultyKey(): ?string
    {
        return $this->difficultyKey;
    }

    public function setDifficultyKey(?string $difficultyKey): self
    {
        $this->difficultyKey = $difficultyKey;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<int, string> $tags
     */
    public function setTags(array $tags): self
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getFeatures(): array
    {
        return $this->features;
    }

    /**
     * @param array<int, string> $features
     */
    public function setFeatures(array $features): self
    {
        $this->features = $features;

        return $this;
    }

    /**
     * Legacy alias.
     */
    public function getName(): string
    {
        return $this->nameKey;
    }

    /**
     * Legacy alias.
     */
    public function setName(string $name): self
    {
        $this->nameKey = $name;

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
