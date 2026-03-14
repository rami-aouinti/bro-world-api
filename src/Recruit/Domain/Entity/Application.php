<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\ApplicationStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_application')]
#[ORM\UniqueConstraint(name: 'uq_recruit_application_applicant_job', columns: ['applicant_id', 'job_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Application implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Applicant::class, inversedBy: 'applications')]
    #[ORM\JoinColumn(name: 'applicant_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Applicant $applicant;

    #[ORM\ManyToOne(targetEntity: Job::class)]
    #[ORM\JoinColumn(name: 'job_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Job $job;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: ApplicationStatus::class, options: [
        'default' => 'WAITING',
    ])]
    private ApplicationStatus $status = ApplicationStatus::WAITING;

    /** @var Collection<int, Interview>|ArrayCollection<int, Interview> */
    #[ORM\OneToMany(targetEntity: Interview::class, mappedBy: 'application', cascade: ['remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $interviews;

    /** @var Collection<int, Offer>|ArrayCollection<int, Offer> */
    #[ORM\OneToMany(targetEntity: Offer::class, mappedBy: 'application', cascade: ['remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $offers;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->interviews = new ArrayCollection();
        $this->offers = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getApplicant(): Applicant
    {
        return $this->applicant;
    }
    public function setApplicant(Applicant $applicant): self
    {
        $this->applicant = $applicant;

        return $this;
    }
    public function getJob(): Job
    {
        return $this->job;
    }
    public function setJob(Job $job): self
    {
        $this->job = $job;

        return $this;
    }
    public function getStatus(): ApplicationStatus
    {
        return $this->status;
    }
    public function getStatusValue(): string
    {
        return $this->status->value;
    }
    public function setStatus(ApplicationStatus|string $status): self
    {
        $this->status = $status instanceof ApplicationStatus ? $status : ApplicationStatus::from($status);

        return $this;
    }

    /**
     * @return Collection<int, Interview>|ArrayCollection<int, Interview>
     */
    public function getInterviews(): Collection|ArrayCollection
    {
        return $this->interviews;
    }

    /**
     * @return Collection<int, Offer>|ArrayCollection<int, Offer>
     */
    public function getOffers(): Collection|ArrayCollection
    {
        return $this->offers;
    }
}
