<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
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

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: TaskStatus::class, options: [
        'default' => TaskStatus::TODO->value,
    ])]
    private TaskStatus $status = TaskStatus::TODO;

    #[ORM\Column(name: 'priority', type: Types::STRING, length: 25, enumType: TaskPriority::class, options: [
        'default' => TaskPriority::MEDIUM->value,
    ])]
    private TaskPriority $priority = TaskPriority::MEDIUM;

    #[ORM\Column(name: 'due_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dueAt = null;

    #[ORM\Column(name: 'estimated_hours', type: Types::FLOAT, nullable: true)]
    private ?float $estimatedHours = null;

    /** @var Collection<int, TaskRequest>|ArrayCollection<int, TaskRequest> */
    #[ORM\OneToMany(targetEntity: TaskRequest::class, mappedBy: 'task')]
    private Collection|ArrayCollection $taskRequests;

    /** @var Collection<int, User>|ArrayCollection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'crm_task_assignee')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection|ArrayCollection $assignees;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->taskRequests = new ArrayCollection();
        $this->assignees = new ArrayCollection();
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function setStatus(TaskStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getPriority(): TaskPriority
    {
        return $this->priority;
    }

    public function setPriority(TaskPriority $priority): self
    {
        $this->priority = $priority;

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

    public function getEstimatedHours(): ?float
    {
        return $this->estimatedHours;
    }

    public function setEstimatedHours(?float $estimatedHours): self
    {
        $this->estimatedHours = $estimatedHours;

        return $this;
    }

    /**
     * @return Collection<int, TaskRequest>|ArrayCollection<int, TaskRequest>
     */
    public function getTaskRequests(): Collection|ArrayCollection
    {
        return $this->taskRequests;
    }

    /**
     * @return Collection<int, User>|ArrayCollection<int, User>
     */
    public function getAssignees(): Collection|ArrayCollection
    {
        return $this->assignees;
    }

    public function addAssignee(User $user): self
    {
        if (!$this->assignees->contains($user)) {
            $this->assignees->add($user);
        }

        return $this;
    }
}
