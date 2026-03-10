<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

use function iconv;
use function is_string;
use function preg_replace;
use function strtolower;
use function substr;
use function trim;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_job')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Job implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Job', 'Job.id'])]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Recruit::class, inversedBy: 'jobs')]
    #[ORM\JoinColumn(name: 'recruit_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Groups(['Job', 'Job.recruit'])]
    private ?Recruit $recruit = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, options: [
        'default' => '',
    ])]
    #[Groups(['Job', 'Job.slug'])]
    private string $slug = '';

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    #[Groups(['Job', 'Job.title'])]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: Company::class)]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['Job', 'Job.company'])]
    private ?Company $company = null;

    #[ORM\OneToOne(targetEntity: Salary::class)]
    #[ORM\JoinColumn(name: 'salary_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['Job', 'Job.salary'])]
    private ?Salary $salary = null;

    #[ORM\Column(name: 'location', type: Types::STRING, length: 255, options: [
        'default' => '',
    ])]
    #[Groups(['Job', 'Job.location'])]
    private string $location = '';

    #[ORM\Column(name: 'contract_type', type: Types::STRING, length: 25, enumType: ContractType::class)]
    #[Groups(['Job', 'Job.contractType'])]
    private ContractType $contractType = ContractType::CDI;

    #[ORM\Column(name: 'work_mode', type: Types::STRING, length: 25, enumType: WorkMode::class)]
    #[Groups(['Job', 'Job.workMode'])]
    private WorkMode $workMode = WorkMode::HYBRID;

    #[ORM\Column(name: 'schedule', type: Types::STRING, length: 25, enumType: Schedule::class)]
    #[Groups(['Job', 'Job.schedule'])]
    private Schedule $schedule = Schedule::FULL_TIME;

    #[ORM\Column(name: 'summary', type: Types::TEXT, options: [
        'default' => '',
    ])]
    #[Groups(['Job', 'Job.summary'])]
    private string $summary = '';

    #[ORM\Column(name: 'match_score', type: Types::SMALLINT, options: [
        'default' => 0,
    ])]
    #[Groups(['Job', 'Job.matchScore'])]
    private int $matchScore = 0;

    #[ORM\Column(name: 'mission_title', type: Types::STRING, length: 255, options: [
        'default' => '',
    ])]
    #[Groups(['Job', 'Job.missionTitle'])]
    private string $missionTitle = '';

    #[ORM\Column(name: 'mission_description', type: Types::TEXT, options: [
        'default' => '',
    ])]
    #[Groups(['Job', 'Job.missionDescription'])]
    private string $missionDescription = '';

    #[ORM\Column(name: 'responsibilities', type: Types::JSON)]
    #[Groups(['Job', 'Job.responsibilities'])]
    private array $responsibilities = [];

    #[ORM\Column(name: 'profile', type: Types::JSON)]
    #[Groups(['Job', 'Job.profile'])]
    private array $profile = [];

    #[ORM\Column(name: 'benefits', type: Types::JSON)]
    #[Groups(['Job', 'Job.benefits'])]
    private array $benefits = [];

    /** @var Collection<int, Badge>|ArrayCollection<int, Badge> */
    #[ORM\ManyToMany(targetEntity: Badge::class)]
    #[ORM\JoinTable(name: 'recruit_job_badge')]
    private Collection|ArrayCollection $badges;

    /** @var Collection<int, Tag>|ArrayCollection<int, Tag> */
    #[ORM\ManyToMany(targetEntity: Tag::class)]
    #[ORM\JoinTable(name: 'recruit_job_tag')]
    private Collection|ArrayCollection $tags;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->badges = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function ensureGeneratedSlug(): self
    {
        $normalizedTitle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->title);
        $base = is_string($normalizedTitle) ? $normalizedTitle : $this->title;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($base));
        $this->slug = trim($slug ?? '', '-');

        if ($this->slug === '') {
            $this->slug = 'job-' . substr($this->getId(), 0, 8);
        }

        return $this;
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getSlug(): string
    {
        return $this->slug;
    }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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
    public function getRecruit(): ?Recruit
    {
        return $this->recruit;
    }
    public function setRecruit(?Recruit $recruit): self
    {
        $this->recruit = $recruit;

        return $this;
    }
    public function getOwner(): ?User
    {
        return $this->owner;
    }
    public function setOwner(?User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
    #[Groups(['Job', 'Job.ownerId'])]
    public function getOwnerId(): ?string
    {
        return $this->owner?->getId();
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
    public function getSalary(): ?Salary
    {
        return $this->salary;
    }
    public function setSalary(?Salary $salary): self
    {
        $this->salary = $salary;

        return $this;
    }
    public function getLocation(): string
    {
        return $this->location;
    }
    public function setLocation(string $location): self
    {
        $this->location = $location;

        return $this;
    }
    public function getContractType(): ContractType
    {
        return $this->contractType;
    }
    public function getContractTypeValue(): string
    {
        return $this->contractType->value;
    }
    public function setContractType(ContractType|string $contractType): self
    {
        $this->contractType = $contractType instanceof ContractType ? $contractType : ContractType::from($contractType);

        return $this;
    }
    public function getWorkMode(): WorkMode
    {
        return $this->workMode;
    }
    public function getWorkModeValue(): string
    {
        return $this->workMode->value;
    }
    public function setWorkMode(WorkMode|string $workMode): self
    {
        $this->workMode = $workMode instanceof WorkMode ? $workMode : WorkMode::from($workMode);

        return $this;
    }
    public function getSchedule(): Schedule
    {
        return $this->schedule;
    }
    public function getScheduleValue(): string
    {
        return $this->schedule->value;
    }
    public function setSchedule(Schedule|string $schedule): self
    {
        $this->schedule = $schedule instanceof Schedule ? $schedule : Schedule::from($schedule);

        return $this;
    }
    public function getSummary(): string
    {
        return $this->summary;
    }
    public function setSummary(string $summary): self
    {
        $this->summary = $summary;

        return $this;
    }
    public function getMatchScore(): int
    {
        return $this->matchScore;
    }
    public function setMatchScore(int $matchScore): self
    {
        $this->matchScore = $matchScore;

        return $this;
    }
    public function getMissionTitle(): string
    {
        return $this->missionTitle;
    }
    public function setMissionTitle(string $missionTitle): self
    {
        $this->missionTitle = $missionTitle;

        return $this;
    }
    public function getMissionDescription(): string
    {
        return $this->missionDescription;
    }
    public function setMissionDescription(string $missionDescription): self
    {
        $this->missionDescription = $missionDescription;

        return $this;
    }
    public function getResponsibilities(): array
    {
        return $this->responsibilities;
    }
    public function setResponsibilities(array $responsibilities): self
    {
        $this->responsibilities = $responsibilities;

        return $this;
    }
    public function getProfile(): array
    {
        return $this->profile;
    }
    public function setProfile(array $profile): self
    {
        $this->profile = $profile;

        return $this;
    }
    public function getBenefits(): array
    {
        return $this->benefits;
    }
    public function setBenefits(array $benefits): self
    {
        $this->benefits = $benefits;

        return $this;
    }
    /**
     * @return Collection<int, Badge>|ArrayCollection<int, Badge>
     */
    public function getBadges(): Collection|ArrayCollection
    {
        return $this->badges;
    }
    public function addBadge(Badge $badge): self
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
        }

return $this;
    }
    public function removeBadge(Badge $badge): self
    {
        $this->badges->removeElement($badge);

        return $this;
    }
    /**
     * @return Collection<int, Tag>|ArrayCollection<int, Tag>
     */
    public function getTags(): Collection|ArrayCollection
    {
        return $this->tags;
    }
    public function addTag(Tag $tag): self
    {
        if (!$this->tags->contains($tag)) {
            $this->tags->add($tag);
        }

return $this;
    }
    public function removeTag(Tag $tag): self
    {
        $this->tags->removeElement($tag);

        return $this;
    }
}
