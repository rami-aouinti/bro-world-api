<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\Blog\Domain\Entity\Blog;
use App\Crm\Domain\Enum\TaskRequestStatus;
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
#[ORM\Table(
    name: 'crm_task_request',
    indexes: [new ORM\Index(name: 'idx_crm_task_request_repository_status', columns: ['repository_id', 'status'])]
)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class TaskRequest implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Task::class, inversedBy: 'taskRequests')]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Task $task = null;

    #[ORM\ManyToOne(targetEntity: CrmRepository::class)]
    #[ORM\JoinColumn(name: 'repository_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?CrmRepository $repository = null;

    #[ORM\OneToOne(targetEntity: Blog::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'blog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Blog $blog = null;

    #[ORM\OneToOne(mappedBy: 'taskRequest', targetEntity: TaskRequestGithubIssue::class, cascade: ['persist', 'remove'])]
    private ?TaskRequestGithubIssue $githubIssue = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 50, enumType: TaskRequestStatus::class, options: [
        'default' => TaskRequestStatus::PENDING->value,
    ])]
    private TaskRequestStatus $status = TaskRequestStatus::PENDING;

    #[ORM\Column(name: 'requested_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $requestedAt;

    #[ORM\Column(name: 'resolved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    /**
     * @var list<array<string,mixed>>
     */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /** @var Collection<int, User>|ArrayCollection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'crm_task_request_assignee')]
    #[ORM\JoinColumn(name: 'task_request_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection|ArrayCollection $assignees;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->requestedAt = new DateTimeImmutable();
        $this->assignees = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function setTask(?Task $task): self
    {
        $this->task = $task;

        return $this;
    }

    public function getBlog(): ?Blog
    {
        return $this->blog;
    }

    public function getRepository(): ?CrmRepository
    {
        return $this->repository;
    }

    public function setRepository(?CrmRepository $repository): self
    {
        $this->repository = $repository;

        return $this;
    }

    public function setBlog(?Blog $blog): self
    {
        $this->blog = $blog;

        return $this;
    }

    public function getGithubIssue(): ?TaskRequestGithubIssue
    {
        return $this->githubIssue;
    }

    public function setGithubIssue(?TaskRequestGithubIssue $githubIssue): self
    {
        $this->githubIssue = $githubIssue;
        if ($githubIssue !== null && $githubIssue->getTaskRequest() !== $this) {
            $githubIssue->setTaskRequest($this);
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

    public function getStatus(): TaskRequestStatus
    {
        return $this->status;
    }

    public function setStatus(TaskRequestStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestedAt(): DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;

        return $this;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): self
    {
        $this->resolvedAt = $resolvedAt;

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
            'repository_id' => $this->getRepository()?->getId(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
            'requested_at' => $this->getRequestedAt(),
            'resolved_at' => $this->getResolvedAt(),
            'assignees' => $this->getAssignees()->toArray(),
            'github_issue' => $this->getGithubIssue()?->toArray(),
        ];
    }
}
