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
#[ORM\Table(name: 'crm_sprint')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Sprint implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'sprints')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    /** @var Collection<int, Task>|ArrayCollection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'sprint')]
    private Collection|ArrayCollection $tasks;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->tasks = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getProject(): ?Project
    {
        return $this->project;
    }
    public function setProject(?Project $project): self
    {
        $this->project = $project;

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

    /**
     * @return Collection<int, Task>|ArrayCollection<int, Task>
     */
    public function getTasks(): Collection|ArrayCollection
    {
        return $this->tasks;
    }
}
