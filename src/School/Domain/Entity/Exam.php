<?php

declare(strict_types=1);

namespace App\School\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'school_exam')]
#[ORM\Index(name: 'idx_school_exam_class_id', columns: ['class_id'])]
#[ORM\Index(name: 'idx_school_exam_teacher_id', columns: ['teacher_id'])]
#[ORM\Index(name: 'idx_school_exam_course_id', columns: ['course_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Exam implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'exams')]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\ManyToOne(targetEntity: Course::class, inversedBy: 'exams')]
    #[ORM\JoinColumn(name: 'course_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Course $course = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(name: 'teacher_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private ?Teacher $teacher = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'type', type: Types::STRING, length: 32, enumType: ExamType::class)]
    private ExamType $type = ExamType::QUIZ;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 32, enumType: ExamStatus::class)]
    private ExamStatus $status = ExamStatus::DRAFT;

    #[ORM\Column(name: 'term', type: Types::STRING, length: 32, enumType: Term::class)]
    private Term $term = Term::TERM_1;

    /** @var Collection<int, Grade>|ArrayCollection<int, Grade> */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'exam')]
    private Collection|ArrayCollection $grades;

    /** @var Collection<int, LearningSessionNote>|ArrayCollection<int, LearningSessionNote> */
    #[ORM\OneToMany(targetEntity: LearningSessionNote::class, mappedBy: 'exam')]
    private Collection|ArrayCollection $learningSessionNotes;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->grades = new ArrayCollection();
        $this->learningSessionNotes = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getSchoolClass(): ?SchoolClass
    {
        return $this->schoolClass;
    }
    public function setSchoolClass(?SchoolClass $schoolClass): self
    {
        $this->schoolClass = $schoolClass;

        return $this;
    }
    public function getCourse(): ?Course
    {
        return $this->course;
    }
    public function setCourse(?Course $course): self
    {
        $this->course = $course;

        return $this;
    }
    public function getTeacher(): ?Teacher
    {
        return $this->teacher;
    }
    public function setTeacher(?Teacher $teacher): self
    {
        $this->teacher = $teacher;

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
    public function getType(): ExamType
    {
        return $this->type;
    }
    public function setType(ExamType $type): self
    {
        $this->type = $type;

        return $this;
    }
    public function getStatus(): ExamStatus
    {
        return $this->status;
    }
    public function setStatus(ExamStatus $status): self
    {
        $this->status = $status;

        return $this;
    }
    public function getTerm(): Term
    {
        return $this->term;
    }
    public function setTerm(Term $term): self
    {
        $this->term = $term;

        return $this;
    }

    /**
     * @return Collection<int, Grade>|ArrayCollection<int, Grade>
     */
    public function getGrades(): Collection|ArrayCollection
    {
        return $this->grades;
    }
}
