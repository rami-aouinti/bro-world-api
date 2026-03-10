<?php

declare(strict_types=1);

namespace App\School\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'school_grade')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Grade implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'grades')]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Exam::class, inversedBy: 'grades')]
    #[ORM\JoinColumn(name: 'exam_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Exam $exam = null;

    #[ORM\Column(name: 'score', type: Types::FLOAT)]
    private float $score = 0.0;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getStudent(): ?Student
    {
        return $this->student;
    }
    public function setStudent(?Student $student): self
    {
        $this->student = $student;

        return $this;
    }
    public function getExam(): ?Exam
    {
        return $this->exam;
    }
    public function setExam(?Exam $exam): self
    {
        $this->exam = $exam;

        return $this;
    }
    public function getScore(): float
    {
        return $this->score;
    }
    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }
}
