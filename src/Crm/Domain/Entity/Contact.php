<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'crm_contact')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Contact implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Crm::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(name: 'crm_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Crm $crm = null;

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Company $company = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 120)]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 120)]
    private string $lastName = '';

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 60, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(name: 'job_title', type: Types::STRING, length: 120, nullable: true)]
    private ?string $jobTitle = null;

    #[ORM\Column(name: 'city', type: Types::STRING, length: 120, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(name: 'score', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $score = 0;

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

    public function getCrm(): ?Crm
    {
        return $this->crm;
    }
    public function setCrm(?Crm $crm): self
    {
        $this->crm = $crm;

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
    public function getFirstName(): string
    {
        return $this->firstName;
    }
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;

        return $this;
    }
    public function getLastName(): string
    {
        return $this->lastName;
    }
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;

        return $this;
    }
    public function getEmail(): ?string
    {
        return $this->email;
    }
    public function setEmail(?string $email): self
    {
        $this->email = $email;

        return $this;
    }
    public function getPhone(): ?string
    {
        return $this->phone;
    }
    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;

        return $this;
    }
    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }
    public function setJobTitle(?string $jobTitle): self
    {
        $this->jobTitle = $jobTitle;

        return $this;
    }
    public function getCity(): ?string
    {
        return $this->city;
    }
    public function setCity(?string $city): self
    {
        $this->city = $city;

        return $this;
    }
    public function getScore(): int
    {
        return $this->score;
    }
    public function setScore(int $score): self
    {
        $this->score = $score;

        return $this;
    }
}
