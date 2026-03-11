<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'crm_company')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Company implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Crm::class, inversedBy: 'companies')]
    #[ORM\JoinColumn(name: 'crm_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Crm $crm = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'industry', type: Types::STRING, length: 120, nullable: true)]
    private ?string $industry = null;

    #[ORM\Column(name: 'website', type: Types::STRING, length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(name: 'contact_email', type: Types::STRING, length: 255, nullable: true)]
    private ?string $contactEmail = null;

    #[ORM\Column(name: 'phone', type: Types::STRING, length: 60, nullable: true)]
    private ?string $phone = null;

    /** @var Collection<int, Project>|ArrayCollection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'company')]
    private Collection|ArrayCollection $projects;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->projects = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getIndustry(): ?string
    {
        return $this->industry;
    }

    public function setIndustry(?string $industry): self
    {
        $this->industry = $industry;

        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): self
    {
        $this->website = $website;

        return $this;
    }

    public function getContactEmail(): ?string
    {
        return $this->contactEmail;
    }

    public function setContactEmail(?string $contactEmail): self
    {
        $this->contactEmail = $contactEmail;

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

    /**
     * @return Collection<int, Project>|ArrayCollection<int, Project>
     */
    public function getProjects(): Collection|ArrayCollection
    {
        return $this->projects;
    }
}
