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
#[ORM\Table(name: 'shop_product')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Product implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Shop::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    #[ORM\ManyToOne(targetEntity: Category::class, inversedBy: 'products')]
    #[ORM\JoinColumn(name: 'category_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Category $category = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'price', type: Types::FLOAT)]
    private float $price = 0.0;

    /** @var Collection<int, Tag>|ArrayCollection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class, inversedBy: 'products')]
    #[ORM\JoinTable(name: 'shop_product_tag')]
    private Collection|ArrayCollection $tags;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->tags = new ArrayCollection();
    }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getShop(): ?Shop { return $this->shop; }
    public function setShop(?Shop $shop): self { $this->shop = $shop; return $this; }
    public function getCategory(): ?Category { return $this->category; }
    public function setCategory(?Category $category): self { $this->category = $category; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getPrice(): float { return $this->price; }
    public function setPrice(float $price): self { $this->price = $price; return $this; }

    /** @return Collection<int, Tag>|ArrayCollection<int, Tag> */
    public function getTags(): Collection|ArrayCollection { return $this->tags; }
    public function addTag(Tag $tag): self { if (!$this->tags->contains($tag)) { $this->tags->add($tag); } return $this; }
    public function removeTag(Tag $tag): self { $this->tags->removeElement($tag); return $this; }
}
