<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Shop\Domain\Enum\ProductStatus;
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

    #[ORM\Column(name: 'sku', type: Types::STRING, length: 64, unique: true)]
    private string $sku = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'photo', type: Types::STRING, length: 1024, options: [
        'default' => '',
    ])]
    private string $photo = '';

    #[ORM\Column(name: 'price', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $price = 0;

    #[ORM\Column(name: 'currency_code', type: Types::STRING, length: 3, options: [
        'default' => 'EUR',
    ])]
    private string $currencyCode = 'EUR';

    #[ORM\Column(name: 'stock', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $stock = 0;

    #[ORM\Column(name: 'coins_amount', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $coinsAmount = 0;

    #[ORM\Column(name: 'is_featured', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $isFeatured = false;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 30, enumType: ProductStatus::class)]
    private ProductStatus $status = ProductStatus::DRAFT;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;

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

    public function getSku(): string
    {
        return $this->sku;
    }

    public function setSku(string $sku): self
    {
        $this->sku = strtoupper(trim($sku));

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

    public function getPrice(): int
    {
        return $this->price;
    }

    public function setPrice(int $price): self
    {
        $this->price = max(0, $price);

        return $this;
    }

    public function getCurrencyCode(): string
    {
        return $this->currencyCode;
    }

    public function setCurrencyCode(string $currencyCode): self
    {
        $this->currencyCode = strtoupper(substr(trim($currencyCode), 0, 3));

        return $this;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): self
    {
        $this->stock = max(0, $stock);

        return $this;
    }

    public function getCoinsAmount(): int
    {
        return $this->coinsAmount;
    }

    public function setCoinsAmount(int $coinsAmount): self
    {
        $this->coinsAmount = max(0, $coinsAmount);

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;

        return $this;
    }

    public function getStatus(): ProductStatus
    {
        return $this->status;
    }

    public function setStatus(ProductStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return Collection<int, Tag>|ArrayCollection<int, Tag>
     */
    public function getTags(): Collection|ArrayCollection
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
