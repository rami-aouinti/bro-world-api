<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Recruit\Domain\Enum\InterviewRecommendation;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_interview_feedback')]
#[ORM\UniqueConstraint(name: 'uq_recruit_feedback_interview_interviewer', columns: ['interview_id', 'interviewer_id'])]
#[ORM\Index(name: 'idx_recruit_feedback_interview', columns: ['interview_id'])]
#[ORM\Index(name: 'idx_recruit_feedback_interviewer', columns: ['interviewer_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class InterviewFeedback implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Interview::class, inversedBy: 'feedbacks')]
    #[ORM\JoinColumn(name: 'interview_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Interview $interview;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'interviewer_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $interviewer;

    #[ORM\Column(name: 'skills_score', type: Types::SMALLINT)]
    private int $skillsScore;

    #[ORM\Column(name: 'communication_score', type: Types::SMALLINT)]
    private int $communicationScore;

    #[ORM\Column(name: 'culture_fit_score', type: Types::SMALLINT)]
    private int $cultureFitScore;

    #[ORM\Column(name: 'recommendation', type: Types::STRING, length: 30, enumType: InterviewRecommendation::class)]
    private InterviewRecommendation $recommendation;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getInterview(): Interview
    {
        return $this->interview;
    }

    public function setInterview(Interview $interview): self
    {
        $this->interview = $interview;

        return $this;
    }

    public function getInterviewer(): User
    {
        return $this->interviewer;
    }

    public function setInterviewer(User $interviewer): self
    {
        $this->interviewer = $interviewer;

        return $this;
    }

    public function getSkillsScore(): int
    {
        return $this->skillsScore;
    }

    public function setSkillsScore(int $skillsScore): self
    {
        $this->skillsScore = $skillsScore;

        return $this;
    }

    public function getCommunicationScore(): int
    {
        return $this->communicationScore;
    }

    public function setCommunicationScore(int $communicationScore): self
    {
        $this->communicationScore = $communicationScore;

        return $this;
    }

    public function getCultureFitScore(): int
    {
        return $this->cultureFitScore;
    }

    public function setCultureFitScore(int $cultureFitScore): self
    {
        $this->cultureFitScore = $cultureFitScore;

        return $this;
    }

    public function getRecommendation(): InterviewRecommendation
    {
        return $this->recommendation;
    }

    public function setRecommendation(InterviewRecommendation|string $recommendation): self
    {
        $this->recommendation = $recommendation instanceof InterviewRecommendation
            ? $recommendation
            : InterviewRecommendation::from($recommendation);

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }
}
