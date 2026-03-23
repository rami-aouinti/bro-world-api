<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'crm_task_request_github_issue')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class TaskRequestGithubIssue
{
    #[ORM\Id]
    #[ORM\OneToOne(targetEntity: TaskRequest::class, inversedBy: 'githubIssue')]
    #[ORM\JoinColumn(name: 'task_request_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TaskRequest $taskRequest = null;

    #[ORM\Column(name: 'provider', type: Types::STRING, length: 30, options: ['default' => 'github'])]
    private string $provider = 'github';

    #[ORM\Column(name: 'repository_full_name', type: Types::STRING, length: 255)]
    private string $repositoryFullName = '';

    #[ORM\Column(name: 'issue_number', type: Types::INTEGER, nullable: true)]
    private ?int $issueNumber = null;

    #[ORM\Column(name: 'issue_node_id', type: Types::STRING, length: 255, nullable: true)]
    private ?string $issueNodeId = null;

    #[ORM\Column(name: 'issue_url', type: Types::STRING, length: 1024, nullable: true)]
    private ?string $issueUrl = null;

    #[ORM\Column(name: 'sync_status', type: Types::STRING, length: 40, options: ['default' => 'pending'])]
    private string $syncStatus = 'pending';

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSyncedAt = null;

    public function getTaskRequest(): ?TaskRequest
    {
        return $this->taskRequest;
    }

    public function setTaskRequest(TaskRequest $taskRequest): self
    {
        $this->taskRequest = $taskRequest;
        if ($taskRequest->getGithubIssue() !== $this) {
            $taskRequest->setGithubIssue($this);
        }

        return $this;
    }

    public function getTaskRequestId(): ?string
    {
        return $this->taskRequest?->getId();
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): self
    {
        $this->provider = $provider;

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

    public function getIssueNumber(): ?int
    {
        return $this->issueNumber;
    }

    public function setIssueNumber(?int $issueNumber): self
    {
        $this->issueNumber = $issueNumber;

        return $this;
    }

    public function getIssueNodeId(): ?string
    {
        return $this->issueNodeId;
    }

    public function setIssueNodeId(?string $issueNodeId): self
    {
        $this->issueNodeId = $issueNodeId;

        return $this;
    }

    public function getIssueUrl(): ?string
    {
        return $this->issueUrl;
    }

    public function setIssueUrl(?string $issueUrl): self
    {
        $this->issueUrl = $issueUrl;

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
    public function toArray(): array
    {
        return [
            'provider' => $this->getProvider(),
            'repositoryFullName' => $this->getRepositoryFullName(),
            'issueNumber' => $this->getIssueNumber(),
            'issueNodeId' => $this->getIssueNodeId(),
            'issueUrl' => $this->getIssueUrl(),
            'syncStatus' => $this->getSyncStatus(),
            'lastSyncedAt' => $this->getLastSyncedAt()?->format(DATE_ATOM),
        ];
    }
}
