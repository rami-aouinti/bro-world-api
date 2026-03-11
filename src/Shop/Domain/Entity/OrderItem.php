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
#[ORM\Table(name: 'shop_order_item')]
#[ORM\Index(name: 'idx_shop_order_item_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_shop_order_item_product_id', columns: ['product_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class OrderItem implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(name: 'product_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Product $product = null;

    #[ORM\Column(name: 'quantity', type: Types::INTEGER)]
    private int $quantity = 1;

    #[ORM\Column(name: 'unit_price_snapshot', type: Types::FLOAT)]
    private float $unitPriceSnapshot = 0.0;

    #[ORM\Column(name: 'line_total', type: Types::FLOAT)]
    private float $lineTotal = 0.0;

    #[ORM\Column(name: 'product_name_snapshot', type: Types::STRING, length: 255)]
    private string $productNameSnapshot = '';

    #[ORM\Column(name: 'product_sku_snapshot', type: Types::STRING, length: 64)]
    private string $productSkuSnapshot = '';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

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
        $this->quantity = max(1, $quantity);

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

    public function getProductNameSnapshot(): string
    {
        return $this->productNameSnapshot;
    }

    public function setProductNameSnapshot(string $productNameSnapshot): self
    {
        $this->productNameSnapshot = trim($productNameSnapshot);

        return $this;
    }

    public function getProductSkuSnapshot(): string
    {
        return $this->productSkuSnapshot;
    }

    public function setProductSkuSnapshot(string $productSkuSnapshot): self
    {
        $this->productSkuSnapshot = strtoupper(trim($productSkuSnapshot));

        return $this;
    }
}

