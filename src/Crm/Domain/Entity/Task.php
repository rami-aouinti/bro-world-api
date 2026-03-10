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
#[ORM\Table(name: 'crm_task')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Task implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Sprint::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'sprint_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Sprint $sprint = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    /** @var Collection<int, TaskRequest>|ArrayCollection<int, TaskRequest> */
    #[ORM\OneToMany(targetEntity: TaskRequest::class, mappedBy: 'task')]
    private Collection|ArrayCollection $taskRequests;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->taskRequests = new ArrayCollection();
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
    public function getSprint(): ?Sprint
    {
        return $this->sprint;
    }
    public function setSprint(?Sprint $sprint): self
    {
        $this->sprint = $sprint;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, TaskRequest>|ArrayCollection<int, TaskRequest>
     */
    public function getTaskRequests(): Collection|ArrayCollection
    {
        return $this->taskRequests;
    }
}
