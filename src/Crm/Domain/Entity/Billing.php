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
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'crm_billing')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Billing implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'billings')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Company $company = null;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 255)]
    private string $label = '';

    #[ORM\Column(name: 'amount', type: Types::FLOAT)]
    private float $amount = 0.0;

    #[ORM\Column(name: 'currency', type: Types::STRING, length: 3, options: [
        'default' => 'EUR',
    ])]
    private string $currency = 'EUR';

    #[ORM\Column(name: 'status', type: Types::STRING, length: 30, options: [
        'default' => 'pending',
    ])]
    private string $status = 'pending';

    #[ORM\Column(name: 'due_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dueAt = null;

    #[ORM\Column(name: 'paid_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $paidAt = null;

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
    public function setId(string $id): self
    {
        $this->id = RamseyUuid::fromString($id);

        return $this;
    }
    public function getCompany(): ?Company
    {
        return $this->company;
    }
    public function setCompany(?Company $company): self
    {
        $this->company = $company;

        return $this;
    }
    public function getLabel(): string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }
    public function getAmount(): float
    {
        return $this->amount;
    }
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }
    public function getDueAt(): ?DateTimeImmutable
    {
        return $this->dueAt;
    }
    public function setDueAt(?DateTimeImmutable $dueAt): self
    {
        $this->dueAt = $dueAt;

        return $this;
    }
    public function getPaidAt(): ?DateTimeImmutable
    {
        return $this->paidAt;
    }
    public function setPaidAt(?DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;

        return $this;
    }
}
