<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(
    name: 'crm_task_request_github_branch',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uq_crm_task_request_gh_branch', columns: ['task_request_id', 'repository_full_name', 'branch_name'])]
)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class TaskRequestGithubBranch implements EntityInterface
{
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: TaskRequest::class, inversedBy: 'githubBranches')]
    #[ORM\JoinColumn(name: 'task_request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaskRequest $taskRequest = null;

    #[ORM\Column(name: 'repository_full_name', type: Types::STRING, length: 255)]
    private string $repositoryFullName = '';

    #[ORM\Column(name: 'branch_name', type: Types::STRING, length: 255)]
    private string $branchName = '';

    #[ORM\Column(name: 'branch_sha', type: Types::STRING, length: 255, nullable: true)]
    private ?string $branchSha = null;

    #[ORM\Column(name: 'branch_url', type: Types::STRING, length: 1024, nullable: true)]
    private ?string $branchUrl = null;

    #[ORM\Column(name: 'issue_number', type: Types::INTEGER, nullable: true)]
    private ?int $issueNumber = null;

    #[ORM\Column(name: 'sync_status', type: Types::STRING, length: 40, options: ['default' => 'pending'])]
    private string $syncStatus = 'pending';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSyncedAt = null;

    /** @var array<string,mixed> */
    #[ORM\Column(name: 'metadata', type: Types::JSON)]
    private array $metadata = [];

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->createdAt = new DateTimeImmutable();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTaskRequest(): ?TaskRequest
    {
        return $this->taskRequest;
    }

    public function setTaskRequest(?TaskRequest $taskRequest): self
    {
        $this->taskRequest = $taskRequest;
        if ($taskRequest !== null && !$taskRequest->getGithubBranches()->contains($this)) {
            $taskRequest->addGithubBranch($this);
        }

        return $this;
    }

    public function getRepositoryFullName(): string
    {
        return $this->repositoryFullName;
    }

    public function setRepositoryFullName(string $repositoryFullName): self
    {
        $this->repositoryFullName = $repositoryFullName;

        return $this;
    }

    public function getBranchName(): string
    {
        return $this->branchName;
    }

    public function setBranchName(string $branchName): self
    {
        $this->branchName = $branchName;

        return $this;
    }

    public function getBranchSha(): ?string
    {
        return $this->branchSha;
    }

    public function setBranchSha(?string $branchSha): self
    {
        $this->branchSha = $branchSha;

        return $this;
    }

    public function getBranchUrl(): ?string
    {
        return $this->branchUrl;
    }

    public function setBranchUrl(?string $branchUrl): self
    {
        $this->branchUrl = $branchUrl;

        return $this;
    }

    public function getIssueNumber(): ?int
    {
        return $this->issueNumber;
    }

    public function setIssueNumber(?int $issueNumber): self
    {
        $this->issueNumber = $issueNumber;

        return $this;
    }

    public function getSyncStatus(): string
    {
        return $this->syncStatus;
    }

    public function setSyncStatus(string $syncStatus): self
    {
        $this->syncStatus = $syncStatus;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getLastSyncedAt(): ?DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string,mixed> $metadata
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'repositoryFullName' => $this->getRepositoryFullName(),
            'branchName' => $this->getBranchName(),
            'branchSha' => $this->getBranchSha(),
            'branchUrl' => $this->getBranchUrl(),
            'issueNumber' => $this->getIssueNumber(),
            'syncStatus' => $this->getSyncStatus(),
            'createdAt' => $this->getCreatedAt()->format(DATE_ATOM),
            'lastSyncedAt' => $this->getLastSyncedAt()?->format(DATE_ATOM),
            'metadata' => $this->getMetadata(),
        ];
    }
}
