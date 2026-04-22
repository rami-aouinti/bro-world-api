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
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

#[ORM\Entity]
#[ORM\Table(
    name: 'crm_repository',
    uniqueConstraints: [new ORM\UniqueConstraint(name: 'uq_crm_repository_project_provider_full_name', columns: ['project_id', 'provider', 'full_name'])],
    indexes: [new ORM\Index(name: 'idx_crm_repository_project_provider_external', columns: ['project_id', 'provider', 'external_id'])]
)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CrmRepository implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'repositories')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\Column(name: 'provider', type: Types::STRING, length: 30, options: [
        'default' => 'github',
    ])]
    private string $provider = 'github';

    #[ORM\Column(name: 'owner', type: Types::STRING, length: 255)]
    private string $owner = '';

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'full_name', type: Types::STRING, length: 255)]
    private string $fullName = '';

    #[ORM\Column(name: 'default_branch', type: Types::STRING, length: 255, nullable: true)]
    private ?string $defaultBranch = null;

    #[ORM\Column(name: 'visibility', type: Types::STRING, length: 20, nullable: true)]
    private ?string $visibility = null;

    #[ORM\Column(name: 'is_private', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $isPrivate = false;

    #[ORM\Column(name: 'primary_language', type: Types::STRING, length: 120, nullable: true)]
    private ?string $primaryLanguage = null;

    #[ORM\Column(name: 'stars_count', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $starsCount = 0;

    #[ORM\Column(name: 'forks_count', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $forksCount = 0;

    #[ORM\Column(name: 'watchers_count', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $watchersCount = 0;

    #[ORM\Column(name: 'open_issues_count', type: Types::INTEGER, options: [
        'default' => 0,
    ])]
    private int $openIssuesCount = 0;

    #[ORM\Column(name: 'is_archived', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $isArchived = false;

    #[ORM\Column(name: 'is_disabled', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    private bool $isDisabled = false;

    #[ORM\Column(name: 'html_url', type: Types::STRING, length: 1024, nullable: true)]
    private ?string $htmlUrl = null;

    #[ORM\Column(name: 'external_id', type: Types::BIGINT, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(name: 'last_synced_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(name: 'last_pushed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $lastPushedAt = null;

    #[ORM\Column(name: 'sync_status', type: Types::STRING, length: 40, options: [
        'default' => 'pending',
    ])]
    private string $syncStatus = 'pending';

    /**
     * @var array<string,mixed>|null
     */
    #[ORM\Column(name: 'payload', type: Types::JSON, nullable: true)]
    private ?array $payload = null;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
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

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): self
    {
        $this->owner = $owner;

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

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function setFullName(string $fullName): self
    {
        $this->fullName = $fullName;

        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): self
    {
        $this->defaultBranch = $defaultBranch;

        return $this;
    }

    public function getVisibility(): ?string
    {
        return $this->visibility;
    }

    public function setVisibility(?string $visibility): self
    {
        $this->visibility = $visibility;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->isPrivate;
    }

    public function setIsPrivate(bool $isPrivate): self
    {
        $this->isPrivate = $isPrivate;

        return $this;
    }

    public function getPrimaryLanguage(): ?string
    {
        return $this->primaryLanguage;
    }

    public function setPrimaryLanguage(?string $primaryLanguage): self
    {
        $this->primaryLanguage = $primaryLanguage;

        return $this;
    }

    public function getStarsCount(): int
    {
        return $this->starsCount;
    }

    public function setStarsCount(int $starsCount): self
    {
        $this->starsCount = $starsCount;

        return $this;
    }

    public function getForksCount(): int
    {
        return $this->forksCount;
    }

    public function setForksCount(int $forksCount): self
    {
        $this->forksCount = $forksCount;

        return $this;
    }

    public function getWatchersCount(): int
    {
        return $this->watchersCount;
    }

    public function setWatchersCount(int $watchersCount): self
    {
        $this->watchersCount = $watchersCount;

        return $this;
    }

    public function getOpenIssuesCount(): int
    {
        return $this->openIssuesCount;
    }

    public function setOpenIssuesCount(int $openIssuesCount): self
    {
        $this->openIssuesCount = $openIssuesCount;

        return $this;
    }

    public function isArchived(): bool
    {
        return $this->isArchived;
    }

    public function setIsArchived(bool $isArchived): self
    {
        $this->isArchived = $isArchived;

        return $this;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function setIsDisabled(bool $isDisabled): self
    {
        $this->isDisabled = $isDisabled;

        return $this;
    }

    public function getHtmlUrl(): ?string
    {
        return $this->htmlUrl;
    }

    public function setHtmlUrl(?string $htmlUrl): self
    {
        $this->htmlUrl = $htmlUrl;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

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

    public function getLastPushedAt(): ?DateTimeImmutable
    {
        return $this->lastPushedAt;
    }

    public function setLastPushedAt(?DateTimeImmutable $lastPushedAt): self
    {
        $this->lastPushedAt = $lastPushedAt;

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

    /**
     * @return array<string,mixed>|null
     */
    public function getPayload(): ?array
    {
        return $this->payload;
    }

    /**
     * @param array<string,mixed>|null $payload
     */
    public function setPayload(?array $payload): self
    {
        $this->payload = $payload;

        return $this;
    }
}
