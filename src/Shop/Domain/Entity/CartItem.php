<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shop_cart_item')]
#[ORM\Index(name: 'idx_shop_cart_item_product_id', columns: ['product_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CartItem implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'cart_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Product $product = null;

    #[ORM\Column(name: 'quantity', type: Types::INTEGER, options: ['default' => 1])]
    private int $quantity = 1;

    #[ORM\Column(name: 'unit_price_snapshot', type: Types::FLOAT)]
    private float $unitPriceSnapshot = 0.0;

    #[ORM\Column(name: 'line_total', type: Types::FLOAT)]
    private float $lineTotal = 0.0;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        $this->cart = $cart;

        return $this;
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = max(0, $quantity);

        return $this;
    }

    public function getUnitPriceSnapshot(): float
    {
        return $this->unitPriceSnapshot;
    }

    public function setUnitPriceSnapshot(float $unitPriceSnapshot): self
    {
        $this->unitPriceSnapshot = max(0, $unitPriceSnapshot);

        return $this;
    }

    public function getLineTotal(): float
    {
        return $this->lineTotal;
    }

    public function setLineTotal(float $lineTotal): self
    {
        $this->lineTotal = max(0, $lineTotal);

        return $this;
    }
}
