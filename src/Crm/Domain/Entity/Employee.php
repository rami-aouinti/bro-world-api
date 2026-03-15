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
#[ORM\Table(name: 'crm_employee')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Employee implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Crm::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'crm_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Crm $crm = null;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 120)]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 120)]
    private string $lastName = '';

    #[ORM\Column(name: 'email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'position_name', type: Types::STRING, length: 120, nullable: true)]
    private ?string $positionName = null;

    #[ORM\Column(name: 'role_name', type: Types::STRING, length: 120, nullable: true)]
    private ?string $roleName = null;

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
    public function getPositionName(): ?string
    {
        return $this->positionName;
    }
    public function setPositionName(?string $positionName): self
    {
        $this->positionName = $positionName;

        return $this;
    }
    public function getRoleName(): ?string
    {
        return $this->roleName;
    }
    public function setRoleName(?string $roleName): self
    {
        $this->roleName = $roleName;

        return $this;
    }
}
