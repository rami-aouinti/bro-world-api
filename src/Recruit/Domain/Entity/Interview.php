<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\InterviewMode;
use App\Recruit\Domain\Enum\InterviewStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

use function array_values;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_interview')]
#[ORM\Index(name: 'idx_recruit_interview_application_scheduled_at', columns: ['application_id', 'scheduled_at'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Interview implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'interviews')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(name: 'scheduled_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $scheduledAt;

    #[ORM\Column(name: 'duration_minutes', type: Types::INTEGER)]
    private int $durationMinutes;

    #[ORM\Column(name: 'mode', type: Types::STRING, length: 25, enumType: InterviewMode::class)]
    private InterviewMode $mode;

    #[ORM\Column(name: 'location_or_url', type: Types::STRING, length: 255)]
    private string $locationOrUrl;

    /**
     * @var array<int, string>
     */
    #[ORM\Column(name: 'interviewer_ids', type: Types::JSON)]
    private array $interviewerIds = [];

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: InterviewStatus::class, options: [
        'default' => 'planned',
    ])]
    private InterviewStatus $status = InterviewStatus::PLANNED;

    #[ORM\Column(name: 'notes', type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /** @var Collection<int, InterviewFeedback>|ArrayCollection<int, InterviewFeedback> */
    #[ORM\OneToMany(targetEntity: InterviewFeedback::class, mappedBy: 'interview', cascade: ['remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $feedbacks;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->feedbacks = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getApplication(): Application
    {
        return $this->application;
    }

    public function setApplication(Application $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getScheduledAt(): \DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(\DateTimeImmutable $scheduledAt): self
    {
        $this->scheduledAt = $scheduledAt;

        return $this;
    }

    public function getDurationMinutes(): int
    {
        return $this->durationMinutes;
    }

    public function setDurationMinutes(int $durationMinutes): self
    {
        $this->durationMinutes = $durationMinutes;

        return $this;
    }

    public function getMode(): InterviewMode
    {
        return $this->mode;
    }

    public function setMode(InterviewMode|string $mode): self
    {
        $this->mode = $mode instanceof InterviewMode ? $mode : InterviewMode::from($mode);

        return $this;
    }

    public function getLocationOrUrl(): string
    {
        return $this->locationOrUrl;
    }

    public function setLocationOrUrl(string $locationOrUrl): self
    {
        $this->locationOrUrl = $locationOrUrl;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getInterviewerIds(): array
    {
        return $this->interviewerIds;
    }

    /**
     * @param array<int, string> $interviewerIds
     */
    public function setInterviewerIds(array $interviewerIds): self
    {
        $this->interviewerIds = array_values($interviewerIds);

        return $this;
    }

    public function getStatus(): InterviewStatus
    {
        return $this->status;
    }

    public function setStatus(InterviewStatus|string $status): self
    {
        $this->status = $status instanceof InterviewStatus ? $status : InterviewStatus::from($status);

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;

        return $this;
    }

    /**
     * @return Collection<int, InterviewFeedback>|ArrayCollection<int, InterviewFeedback>
     */
    public function getFeedbacks(): Collection|ArrayCollection
    {
        return $this->feedbacks;
    }
}
