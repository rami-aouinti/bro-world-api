<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Shop\Domain\Enum\PaymentStatus;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shop_payment_transaction')]
#[ORM\UniqueConstraint(name: 'uniq_shop_payment_provider_reference', columns: ['provider', 'provider_reference'])]
#[ORM\UniqueConstraint(name: 'uniq_shop_payment_webhook_key', columns: ['webhook_idempotence_key'])]
#[ORM\UniqueConstraint(name: 'uniq_shop_payment_coins_credit_reference', columns: ['coins_credit_reference'])]
#[ORM\Index(name: 'idx_shop_payment_order_id', columns: ['order_id'])]
#[ORM\Index(name: 'idx_shop_payment_status', columns: ['status'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class PaymentTransaction implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Order::class)]
    #[ORM\JoinColumn(name: 'order_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\Column(name: 'provider', type: Types::STRING, length: 80)]
    private string $provider = '';

    #[ORM\Column(name: 'provider_reference', type: Types::STRING, length: 190)]
    private string $providerReference = '';

    #[ORM\Column(name: 'amount', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $amount = 0;

    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(name: 'status', type: Types::STRING, length: 40, enumType: PaymentStatus::class)]
    private PaymentStatus $status = PaymentStatus::CREATED;

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'payload', type: Types::JSON)]
    private array $payload = [];

    #[ORM\Column(name: 'webhook_idempotence_key', type: Types::STRING, length: 190, nullable: true)]
    private ?string $webhookIdempotenceKey = null;

    #[ORM\Column(name: 'coins_credit_reference', type: Types::STRING, length: 190, nullable: true)]
    private ?string $coinsCreditReference = null;

    #[ORM\Column(name: 'coins_credited_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $coinsCreditedAt = null;

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

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = trim($provider);

        return $this;
    }

    public function getProviderReference(): string
    {
        return $this->providerReference;
    }

    public function setProviderReference(string $providerReference): self
    {
        $this->providerReference = trim($providerReference);

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): self
    {
        $this->amount = max(0, $amount);

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper(trim($currency));

        return $this;
    }

    public function getStatus(): PaymentStatus
    {
        return $this->status;
    }

    public function setStatus(PaymentStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }

    public function getWebhookIdempotenceKey(): ?string
    {
        return $this->webhookIdempotenceKey;
    }

    public function setWebhookIdempotenceKey(?string $webhookIdempotenceKey): self
    {
        $this->webhookIdempotenceKey = $webhookIdempotenceKey !== null ? trim($webhookIdempotenceKey) : null;

        return $this;
    }

    public function getCoinsCreditReference(): ?string
    {
        return $this->coinsCreditReference;
    }

    public function setCoinsCreditReference(?string $coinsCreditReference): self
    {
        $this->coinsCreditReference = $coinsCreditReference !== null ? trim($coinsCreditReference) : null;

        return $this;
    }

    public function getCoinsCreditedAt(): ?DateTimeImmutable
    {
        return $this->coinsCreditedAt;
    }

    public function setCoinsCreditedAt(?DateTimeImmutable $coinsCreditedAt): self
    {
        $this->coinsCreditedAt = $coinsCreditedAt;

        return $this;
    }
}
