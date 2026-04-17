<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\Blog\Domain\Entity\Blog;
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
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

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
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Sprint::class, inversedBy: 'tasks')]
    #[ORM\JoinColumn(name: 'sprint_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Sprint $sprint = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subTasks')]
    #[ORM\JoinColumn(name: 'parent_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parentTask = null;

    /** @var Collection<int, self>|ArrayCollection<int, self> */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentTask')]
    private Collection|ArrayCollection $subTasks;

    #[ORM\OneToOne(targetEntity: Blog::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'blog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Blog $blog = null;

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

    /**
     * @var array<string,mixed>|null
     */
    #[ORM\Column(name: 'github_issue', type: Types::JSON, nullable: true)]
    private ?array $githubIssue = null;

    /**
     * @var list<array<string,mixed>>
     */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /** @var Collection<int, TaskRequest>|ArrayCollection<int, TaskRequest> */
    #[ORM\OneToMany(targetEntity: TaskRequest::class, mappedBy: 'task')]
    private Collection|ArrayCollection $taskRequests;

    /** @var Collection<int, User>|ArrayCollection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'crm_task_assignee')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection|ArrayCollection $assignees;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->taskRequests = new ArrayCollection();
        $this->assignees = new ArrayCollection();
        $this->subTasks = new ArrayCollection();
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

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function setBlog(?Blog $blog): self
    {
        $this->blog = $blog;

        return $this;
    }

    public function getParentTask(): ?self
    {
        return $this->parentTask;
    }

    public function setParentTask(?self $parentTask): self
    {
        $this->parentTask = $parentTask;

        return $this;
    }

    /**
     * @return Collection<int, self>|ArrayCollection<int, self>
     */
    public function getSubTasks(): Collection|ArrayCollection
    {
        return $this->subTasks;
    }

    public function addSubTask(self $subTask): self
    {
        if (!$this->subTasks->contains($subTask)) {
            $this->subTasks->add($subTask);
            $subTask->setParentTask($this);
        }

        return $this;
    }

    public function removeSubTask(self $subTask): self
    {
        if ($this->subTasks->removeElement($subTask) && $subTask->getParentTask() === $this) {
            $subTask->setParentTask(null);
        }

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
     * @return array<string,mixed>|null
     */
    public function getGithubIssue(): ?array
    {
        return $this->githubIssue;
    }

    /**
     * @param array<string,mixed>|null $githubIssue
     */
    public function setGithubIssue(?array $githubIssue): self
    {
        $this->githubIssue = $githubIssue;

        return $this;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /**
     * @param list<array<string,mixed>> $attachments
     */
    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /**
     * @param array<string,mixed> $attachment
     */
    public function addAttachment(array $attachment): self
    {
        $this->attachments[] = $attachment;

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

    public function removeAssignee(User $user): self
    {
        if ($this->assignees->contains($user)) {
            $this->assignees->removeElement($user);
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'priority' => $this->getPriority(),
            'dueAt' => $this->getDueAt(),
            'estimatedHours' => $this->getEstimatedHours(),
            'request' => $this->getTaskRequests()->first()?->toArray(),
        ];
    }
}
