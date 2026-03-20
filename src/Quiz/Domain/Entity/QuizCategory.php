<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\General\Domain\Color\Traits\ColorPaletteTrait;
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

#[ORM\Entity]
#[ORM\Table(name: 'quiz_category', indexes: [
    new ORM\Index(name: 'idx_quiz_category_slug', columns: ['slug']),
    new ORM\Index(name: 'idx_quiz_category_active', columns: ['is_active']),
])]
class QuizCategory implements EntityInterface
{
    use Timestampable;
    use Uuid;
    use ColorPaletteTrait;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 120)]
    private string $name = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 120, unique: true)]
    private string $slug = '';

    #[ORM\Column(name: 'position', type: Types::INTEGER, options: ['default' => 1])]
    private int $position = 1;

    #[ORM\Column(name: 'is_active', type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'color', type: Types::STRING, length: 7, options: ['default' => '#64748B'])]
    private string $color = '#64748B';

    /**
     * @var Collection<int, QuizQuestion>
     */
    #[ORM\OneToMany(targetEntity: QuizQuestion::class, mappedBy: 'category')]
    private Collection $questions;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->questions = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = max(1, $position);

        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getColor(): string
    {
        return $this->color;
    }

    public function setColor(string $color): self
    {
        $this->color = self::normalizeHexColor($color);

        return $this;
    }
}

