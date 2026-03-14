<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

use App\Crm\Domain\Enum\ProjectStatus;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\User\Domain\Entity\User;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;

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

    /** @var list<array<string,mixed>> */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /** @var list<array<string,mixed>> */
    #[ORM\Column(name: 'wiki_pages', type: Types::JSON)]
    private array $wikiPages = [];

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

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->tasks = new ArrayCollection();
        $this->sprints = new ArrayCollection();
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

    /** @return list<array<string,mixed>> */
    public function getAttachments(): array
    {
        return $this->attachments;
    }

    /** @param list<array<string,mixed>> $attachments */
    public function setAttachments(array $attachments): self
    {
        $this->attachments = $attachments;

        return $this;
    }

    /** @param array<string,mixed> $attachment */
    public function addAttachment(array $attachment): self
    {
        $this->attachments[] = $attachment;

        return $this;
    }

    /** @return list<array<string,mixed>> */
    public function getWikiPages(): array
    {
        return $this->wikiPages;
    }

    /** @param list<array<string,mixed>> $wikiPages */
    public function setWikiPages(array $wikiPages): self
    {
        $this->wikiPages = $wikiPages;

        return $this;
    }

    /** @param array<string,mixed> $wikiPage */
    public function addWikiPage(array $wikiPage): self
    {
        $this->wikiPages[] = $wikiPage;

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
