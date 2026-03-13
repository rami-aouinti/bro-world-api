<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Shop\Domain\Enum\OrderStatus;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shop_order')]
#[ORM\Index(name: 'idx_shop_order_status', columns: ['status'])]
#[ORM\Index(name: 'idx_shop_order_created_at', columns: ['created_at'])]
#[ORM\Index(name: 'idx_shop_order_shop_id', columns: ['shop_id'])]
#[ORM\Index(name: 'idx_shop_order_user_id', columns: ['user_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Order implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Shop::class)]
    #[ORM\JoinColumn(name: 'shop_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Shop $shop = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 30, enumType: OrderStatus::class)]
    private OrderStatus $status = OrderStatus::DRAFT;

    #[ORM\Column(name: 'subtotal', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $subtotal = 0;

    #[ORM\Column(name: 'billing_address', type: Types::TEXT)]
    private string $billingAddress = '';

    #[ORM\Column(name: 'shipping_address', type: Types::TEXT)]
    private string $shippingAddress = '';

    #[ORM\Column(name: 'email', type: Types::STRING, length: 190)]
    private string $email = '';

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 40)]
    private string $phone = '';

    #[ORM\Column(name: 'shipping_method', type: Types::STRING, length: 80)]
    private string $shippingMethod = '';

    /** @var Collection<int, OrderItem>|ArrayCollection<int, OrderItem> */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $items;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->items = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
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

    public function getStatus(): OrderStatus
    {
        return $this->status;
    }

    public function setStatus(OrderStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getSubtotal(): int
    {
        return $this->subtotal;
    }

    public function setSubtotal(int $subtotal): self
    {
        $this->subtotal = max(0, $subtotal);

        return $this;
    }

    public function getBillingAddress(): string
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(string $billingAddress): self
    {
        $this->billingAddress = trim($billingAddress);

        return $this;
    }

    public function getShippingAddress(): string
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(string $shippingAddress): self
    {
        $this->shippingAddress = trim($shippingAddress);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = trim($email);

        return $this;
    }

    public function getPhone(): string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): self
    {
        $this->phone = trim($phone);

        return $this;
    }

    public function getShippingMethod(): string
    {
        return $this->shippingMethod;
    }

    public function setShippingMethod(string $shippingMethod): self
    {
        $this->shippingMethod = trim($shippingMethod);

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>|ArrayCollection<int, OrderItem>
     */
    public function getItems(): Collection|ArrayCollection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setOrder($this);
        }

        return $this;
    }
}
