<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'crm_github_sync_job', indexes: [
    new ORM\Index(name: 'idx_crm_gh_sync_job_app_status', columns: ['application_slug', 'status']),
])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CrmGithubSyncJob implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'application_slug', type: Types::STRING, length: 120)]
    private string $applicationSlug = '';

    #[ORM\Column(name: 'owner', type: Types::STRING, length: 255)]
    private string $owner = '';

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'finished_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 40)]
    private string $status = 'queued';

    #[ORM\Column(name: 'projects_created', type: Types::INTEGER)]
    private int $projectsCreated = 0;

    #[ORM\Column(name: 'repos_attached', type: Types::INTEGER)]
    private int $reposAttached = 0;

    #[ORM\Column(name: 'issues_imported', type: Types::INTEGER)]
    private int $issuesImported = 0;

    #[ORM\Column(name: 'errors_count', type: Types::INTEGER)]
    private int $errorsCount = 0;

    /**
     * @var array<int,mixed>
     */
    #[ORM\Column(name: 'errors', type: Types::JSON)]
    private array $errors = [];

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column(name: 'parameters', type: Types::JSON)]
    private array $parameters = [];

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getApplicationSlug(): string
    {
        return $this->applicationSlug;
    }
    public function setApplicationSlug(string $applicationSlug): self
    {
        $this->applicationSlug = trim($applicationSlug);

        return $this;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }
    public function setOwner(string $owner): self
    {
        $this->owner = trim($owner);

        return $this;
    }

    public function getStartedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }
    public function setStartedAt(?DateTimeImmutable $startedAt): self
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
    public function setFinishedAt(?DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->status = trim($status);

        return $this;
    }

    public function getProjectsCreated(): int
    {
        return $this->projectsCreated;
    }
    public function setProjectsCreated(int $projectsCreated): self
    {
        $this->projectsCreated = $projectsCreated;

        return $this;
    }

    public function getReposAttached(): int
    {
        return $this->reposAttached;
    }
    public function setReposAttached(int $reposAttached): self
    {
        $this->reposAttached = $reposAttached;

        return $this;
    }

    public function getIssuesImported(): int
    {
        return $this->issuesImported;
    }
    public function setIssuesImported(int $issuesImported): self
    {
        $this->issuesImported = $issuesImported;

        return $this;
    }

    public function getErrorsCount(): int
    {
        return $this->errorsCount;
    }
    public function setErrorsCount(int $errorsCount): self
    {
        $this->errorsCount = $errorsCount;

        return $this;
    }

    /**
     * @return array<int,mixed>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @param array<int,mixed> $errors
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param array<string,mixed> $parameters
     */
    public function setParameters(array $parameters): self
    {
        $this->parameters = $parameters;

        return $this;
    }
}
