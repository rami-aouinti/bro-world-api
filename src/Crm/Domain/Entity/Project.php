<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\Blog\Domain\Entity\Blog;
use App\Crm\Domain\Enum\ProjectStatus;
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

use function array_map;
use function array_pad;
use function explode;
use function is_array;
use function strtolower;
use function trim;

#[ORM\Entity]
#[ORM\Table(name: 'crm_project')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Project implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'projects')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Company $company = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'code', type: Types::STRING, length: 80, nullable: true)]
    private ?string $code = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: ProjectStatus::class, options: [
        'default' => ProjectStatus::PLANNED->value,
    ])]
    private ProjectStatus $status = ProjectStatus::PLANNED;

    #[ORM\Column(name: 'started_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'due_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $dueAt = null;

    /**
     * @var list<array<string,mixed>>
     */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /**
     * @var list<array<string,mixed>>
     */
    #[ORM\Column(name: 'wiki_pages', type: Types::JSON)]
    private array $wikiPages = [];

    #[ORM\Column(name: 'github_token', type: Types::STRING, length: 255, nullable: true)]
    private ?string $githubToken = null;

    #[ORM\OneToOne(targetEntity: Blog::class, cascade: ['persist'])]
    #[ORM\JoinColumn(name: 'blog_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Blog $blog = null;

    #[ORM\Column(name: 'provisioning_status', type: Types::STRING, length: 40, options: [
        'default' => 'pending',
    ])]
    private string $provisioningStatus = 'pending';

    /**
     * @var array<string,mixed>
     */
    #[ORM\Column(name: 'github_resource_ids', type: Types::JSON, nullable: true)]
    private ?array $githubResourceIds = null;

    /** @var Collection<int, CrmRepository>|ArrayCollection<int, CrmRepository> */
    #[ORM\OneToMany(targetEntity: CrmRepository::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $repositories;

    /** @var Collection<int, Task>|ArrayCollection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project')]
    private Collection|ArrayCollection $tasks;

    /** @var Collection<int, Sprint>|ArrayCollection<int, Sprint> */
    #[ORM\OneToMany(targetEntity: Sprint::class, mappedBy: 'project')]
    private Collection|ArrayCollection $sprints;

    /** @var Collection<int, User>|ArrayCollection<int, User> */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'crm_project_assignee')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection|ArrayCollection $assignees;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->tasks = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->repositories = new ArrayCollection();
        $this->assignees = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): self
    {
        $this->company = $company;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(?string $code): self
    {
        $this->code = $code;

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

    public function getStatus(): ProjectStatus
    {
        return $this->status;
    }

    public function setStatus(ProjectStatus $status): self
    {
        $this->status = $status;

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

    public function getDueAt(): ?DateTimeImmutable
    {
        return $this->dueAt;
    }

    public function setDueAt(?DateTimeImmutable $dueAt): self
    {
        $this->dueAt = $dueAt;

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
     * @return list<array<string,mixed>>
     */
    public function getWikiPages(): array
    {
        return $this->wikiPages;
    }

    /**
     * @param list<array<string,mixed>> $wikiPages
     */
    public function setWikiPages(array $wikiPages): self
    {
        $this->wikiPages = $wikiPages;

        return $this;
    }

    /**
     * @param array<string,mixed> $wikiPage
     */
    public function addWikiPage(array $wikiPage): self
    {
        $this->wikiPages[] = $wikiPage;

        return $this;
    }

    public function getGithubToken(): ?string
    {
        return $this->githubToken;
    }

    public function setGithubToken(?string $githubToken): self
    {
        $this->githubToken = $githubToken;

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

    public function getProvisioningStatus(): string
    {
        return $this->provisioningStatus;
    }

    public function setProvisioningStatus(string $provisioningStatus): self
    {
        $this->provisioningStatus = $provisioningStatus;

        return $this;
    }

    /**
     * @return array<string,mixed>
     */
    public function getGithubResourceIds(): array
    {
        return is_array($this->githubResourceIds) ? $this->githubResourceIds : [];
    }

    /**
     * @param array<string,mixed>|null $githubResourceIds
     */
    public function setGithubResourceIds(?array $githubResourceIds): self
    {
        $this->githubResourceIds = $githubResourceIds;

        return $this;
    }

    /**
     * @return Collection<int, CrmRepository>|ArrayCollection<int, CrmRepository>
     */
    public function getRepositories(): Collection|ArrayCollection
    {
        return $this->repositories;
    }

    /**
     * Backward-compatible accessor used by read/services and controllers.
     *
     * @return list<array<string,mixed>>
     */
    public function getGithubRepositories(): array
    {
        return array_map(static fn (CrmRepository $repository): array => [
            'id' => $repository->getId(),
            'provider' => $repository->getProvider(),
            'owner' => $repository->getOwner(),
            'name' => $repository->getName(),
            'fullName' => $repository->getFullName(),
            'defaultBranch' => $repository->getDefaultBranch(),
            'isPrivate' => $repository->isPrivate(),
            'htmlUrl' => $repository->getHtmlUrl(),
            'externalId' => $repository->getExternalId(),
            'nodeId' => $repository->getPayload()['nodeId'] ?? null,
            'lastSyncedAt' => $repository->getLastSyncedAt()?->format(DATE_ATOM),
            'syncStatus' => $repository->getSyncStatus(),
            'payload' => $repository->getPayload(),
        ], $this->repositories->toArray());
    }

    /**
     * @param list<array{fullName:string,defaultBranch?:string|null,provider?:string,owner?:string,name?:string,isPrivate?:bool,htmlUrl?:string|null,externalId?:string|int|null,syncStatus?:string,payload?:array<string,mixed>|null}> $githubRepositories
     */
    public function setGithubRepositories(array $githubRepositories): self
    {
        $this->repositories->clear();

        foreach ($githubRepositories as $githubRepository) {
            $fullName = trim((string)($githubRepository['fullName'] ?? ''));
            if ($fullName === '') {
                continue;
            }

            [$fallbackOwner, $fallbackName] = array_pad(explode('/', $fullName, 2), 2, '');

            $repository = (new CrmRepository())
                ->setProject($this)
                ->setProvider(strtolower(trim((string)($githubRepository['provider'] ?? 'github'))))
                ->setOwner(trim((string)($githubRepository['owner'] ?? $fallbackOwner)))
                ->setName(trim((string)($githubRepository['name'] ?? $fallbackName)))
                ->setFullName($fullName)
                ->setDefaultBranch(($githubRepository['defaultBranch'] ?? null) !== '' ? ($githubRepository['defaultBranch'] ?? null) : null)
                ->setIsPrivate((bool)($githubRepository['isPrivate'] ?? false))
                ->setHtmlUrl(isset($githubRepository['htmlUrl']) ? (string)$githubRepository['htmlUrl'] : null)
                ->setExternalId(isset($githubRepository['externalId']) ? (string)$githubRepository['externalId'] : null)
                ->setSyncStatus(trim((string)($githubRepository['syncStatus'] ?? 'pending')))
                ->setPayload(isset($githubRepository['payload']) && is_array($githubRepository['payload']) ? $githubRepository['payload'] : null);

            $this->addRepository($repository);
        }

        return $this;
    }

    public function addRepository(CrmRepository $repository): self
    {
        if (!$this->repositories->contains($repository)) {
            $this->repositories->add($repository);
            $repository->setProject($this);
        }

        return $this;
    }

    public function removeRepository(CrmRepository $repository): self
    {
        if ($this->repositories->removeElement($repository) && $repository->getProject() === $this) {
            $repository->setProject(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Task>|ArrayCollection<int, Task>
     */
    public function getTasks(): Collection|ArrayCollection
    {
        return $this->tasks;
    }

    /**
     * @return Collection<int, Sprint>|ArrayCollection<int, Sprint>
     */
    public function getSprints(): Collection|ArrayCollection
    {
        return $this->sprints;
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
            'name' => $this->getName(),
            'code' => $this->getCode(),
            'description' => $this->getDescription(),
            'status' => $this->getStatus(),
        ];
    }
}
