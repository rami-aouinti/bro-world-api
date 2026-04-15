<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

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
#[ORM\Table(name: 'shop_category')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Category implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Shop::class, inversedBy: 'categories')]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 120)]
    private string $slug = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'photo', type: Types::STRING, length: 1024, options: [
        'default' => '',
    ])]
    private string $photo = '';

    /** @var Collection<int, Product>|ArrayCollection<int, Product> */
    #[ORM\OneToMany(targetEntity: Product::class, mappedBy: 'category')]
    private Collection|ArrayCollection $products;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->products = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getShop(): ?Shop
    {
        return $this->shop;
    }

    public function setShop(?Shop $shop): self
    {
        $this->shop = $shop;

        return $this;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getPhoto(): string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): self
    {
        $this->photo = trim($photo);

        return $this;
    }

    /**
     * @return Collection<int, Product>|ArrayCollection<int, Product>
     */
    public function getProducts(): Collection|ArrayCollection
    {
        return $this->products;
    }
}
