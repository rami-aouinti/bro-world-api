<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\OfferStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_offer')]
#[ORM\Index(name: 'idx_recruit_offer_application_created_at', columns: ['application_id', 'created_at'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Offer implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'offers')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(name: 'salary_proposed', type: Types::FLOAT)]
    private float $salaryProposed;

    #[ORM\Column(name: 'start_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startDate;

    #[ORM\Column(name: 'contract_type', type: Types::STRING, length: 25, enumType: ContractType::class)]
    private ContractType $contractType;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: OfferStatus::class, options: ['default' => 'DRAFT'])]
    private OfferStatus $status = OfferStatus::DRAFT;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getApplication(): Application
    {
        return $this->application;
    }

    public function setApplication(Application $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getSalaryProposed(): float
    {
        return $this->salaryProposed;
    }

    public function setSalaryProposed(float $salaryProposed): self
    {
        $this->salaryProposed = $salaryProposed;

        return $this;
    }

    public function getStartDate(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getContractType(): ContractType
    {
        return $this->contractType;
    }

    public function setContractType(ContractType|string $contractType): self
    {
        $this->contractType = $contractType instanceof ContractType ? $contractType : ContractType::from($contractType);

        return $this;
    }

    public function getStatus(): OfferStatus
    {
        return $this->status;
    }

    public function setStatus(OfferStatus|string $status): self
    {
        $this->status = $status instanceof OfferStatus ? $status : OfferStatus::from($status);

        return $this;
    }
}
