<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'crm_github_webhook_event', uniqueConstraints: [new ORM\UniqueConstraint(name: 'uq_crm_github_webhook_event_delivery_id', columns: ['delivery_id'])])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CrmGithubWebhookEvent implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'delivery_id', type: Types::STRING, length: 255)]
    private string $deliveryId = '';

    #[ORM\Column(name: 'event_name', type: Types::STRING, length: 80)]
    private string $eventName = '';

    #[ORM\Column(name: 'event_action', type: Types::STRING, length: 80, nullable: true)]
    private ?string $eventAction = null;

    #[ORM\Column(name: 'repository_full_name', type: Types::STRING, length: 255, nullable: true)]
    private ?string $repositoryFullName = null;

    #[ORM\Column(name: 'signature', type: Types::STRING, length: 255, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(name: 'checksum', type: Types::STRING, length: 64)]
    private string $checksum = '';

    #[ORM\Column(name: 'status', type: Types::STRING, length: 40)]
    private string $status = 'received';

    #[ORM\Column(name: 'processed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $processedAt = null;

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column(name: 'payload', type: Types::JSON)]
    private array $payload = [];

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getDeliveryId(): string
    {
        return $this->deliveryId;
    }
    public function setDeliveryId(string $deliveryId): self
    {
        $this->deliveryId = trim($deliveryId);

        return $this;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }
    public function setEventName(string $eventName): self
    {
        $this->eventName = trim($eventName);

        return $this;
    }

    public function getEventAction(): ?string
    {
        return $this->eventAction;
    }
    public function setEventAction(?string $eventAction): self
    {
        $this->eventAction = $eventAction !== null ? trim($eventAction) : null;

        return $this;
    }

    public function getRepositoryFullName(): ?string
    {
        return $this->repositoryFullName;
    }
    public function setRepositoryFullName(?string $repositoryFullName): self
    {
        $this->repositoryFullName = $repositoryFullName !== null ? trim($repositoryFullName) : null;

        return $this;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }
    public function setSignature(?string $signature): self
    {
        $this->signature = $signature !== null ? trim($signature) : null;

        return $this;
    }

    public function getChecksum(): string
    {
        return $this->checksum;
    }
    public function setChecksum(string $checksum): self
    {
        $this->checksum = trim($checksum);

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->status = trim($status);

        return $this;
    }

    public function getProcessedAt(): ?DateTimeImmutable
    {
        return $this->processedAt;
    }
    public function setProcessedAt(?DateTimeImmutable $processedAt): self
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }
    /**
     * @param array<string,mixed> $payload
     */
    public function setPayload(array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }
}
