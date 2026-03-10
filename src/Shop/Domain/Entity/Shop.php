<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shop')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Shop implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\OneToOne(targetEntity: PlatformApplication::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?PlatformApplication $application = null;

    /** @var Collection<int, Category>|ArrayCollection<int, Category> */
    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'shop')]
    private Collection|ArrayCollection $categories;

    /** @var Collection<int, Product>|ArrayCollection<int, Product> */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'shop')]
    private Collection|ArrayCollection $products;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->categories = new ArrayCollection();
        $this->products = new ArrayCollection();
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
    public function getApplication(): ?PlatformApplication
    {
        return $this->application;
    }
    public function setApplication(?PlatformApplication $application): self
    {
        $this->application = $application;

        return $this;
    }

    /**
     * @return Collection<int, Category>|ArrayCollection<int, Category>
     */
    public function getCategories(): Collection|ArrayCollection
    {
        return $this->categories;
    }

    /**
     * @return Collection<int, Product>|ArrayCollection<int, Product>
     */
    public function getProducts(): Collection|ArrayCollection
    {
        return $this->products;
    }
}
