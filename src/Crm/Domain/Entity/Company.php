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

    /** @var Collection<int, Project>|ArrayCollection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'company')]
    private Collection|ArrayCollection $projects;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->projects = new ArrayCollection();
    }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getCrm(): ?Crm { return $this->crm; }
    public function setCrm(?Crm $crm): self { $this->crm = $crm; return $this; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, Project>|ArrayCollection<int, Project> */
    public function getProjects(): Collection|ArrayCollection { return $this->projects; }
}
