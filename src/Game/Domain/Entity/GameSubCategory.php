<?php

declare(strict_types=1);

namespace App\Game\Domain\Entity;

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
#[ORM\Table(name: 'game_sub_category')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class GameSubCategory implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: GameCategory::class, inversedBy: 'subCategories')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?GameCategory $category = null;

    #[ORM\Column(name: 'sub_category_key', type: Types::STRING, length: 100, unique: true)]
    private string $key = '';

    #[ORM\Column(name: 'name_key', type: Types::STRING, length: 255)]
    private string $nameKey = '';

    #[ORM\Column(name: 'description_key', type: Types::STRING, length: 255, nullable: true)]
    private ?string $descriptionKey = null;

    #[ORM\Column(name: 'img', type: Types::STRING, length: 255, nullable: true)]
    private ?string $img = null;

    #[ORM\Column(name: 'icon', type: Types::STRING, length: 255, nullable: true)]
    private ?string $icon = null;

    /**
     * @var Collection<int, Game>
     */
    #[ORM\OneToMany(targetEntity: Game::class, mappedBy: 'subCategory')]
    private Collection $games;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->games = new ArrayCollection();
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

    /**
     * @return Collection<int, Game>
     */
    public function getGames(): Collection
    {
        return $this->games;
    }
}
