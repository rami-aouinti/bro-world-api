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
#[ORM\Table(name: 'school_learning_session_note')]
#[ORM\Index(name: 'idx_school_lsn_student_id', columns: ['student_id'])]
#[ORM\Index(name: 'idx_school_lsn_exam_id', columns: ['exam_id'])]
#[ORM\Index(name: 'idx_school_lsn_course_id', columns: ['course_id'])]
#[ORM\UniqueConstraint(name: 'uniq_school_lsn_exam_student', columns: ['exam_id', 'student_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class LearningSessionNote implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Student::class, inversedBy: 'learningSessionNotes')]
    #[ORM\JoinColumn(name: 'student_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Student $student = null;

    #[ORM\ManyToOne(targetEntity: Exam::class, inversedBy: 'learningSessionNotes')]
    #[ORM\JoinColumn(name: 'exam_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Exam $exam = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'learningSessionNotes')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\Column(name: 'score', type: Types::FLOAT)]
    private float $score = 0.0;

    #[ORM\Column(name: 'passed', type: Types::BOOLEAN)]
    private bool $passed = false;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function setStudent(?Student $student): self
    {
        $this->student = $student;

        return $this;
    }
    public function setExam(?Exam $exam): self
    {
        $this->exam = $exam;

        return $this;
    }
    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }
    public function setScore(float $score): self
    {
        $this->score = $score;

        return $this;
    }
    public function setPassed(bool $passed): self
    {
        $this->passed = $passed;

        return $this;
    }
}
