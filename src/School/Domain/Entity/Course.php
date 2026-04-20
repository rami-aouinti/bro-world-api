<?php

declare(strict_types=1);

namespace App\School\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'school_course')]
#[ORM\Index(columns: ['class_id'], name: 'idx_school_course_class_id')]
#[ORM\Index(columns: ['teacher_id'], name: 'idx_school_course_teacher_id')]
#[ORM\UniqueConstraint(name: 'uniq_school_course_class_name', columns: ['class_id', 'name'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Course implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class, inversedBy: 'courses')]
    #[ORM\JoinColumn(name: 'teacher_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $teacher = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'content_html', type: Types::TEXT, nullable: true)]
    private ?string $contentHtml = null;

    /**
     * @var list<array<string,mixed>>
     */
    #[ORM\Column(name: 'attachments', type: Types::JSON)]
    private array $attachments = [];

    /** @var Collection<int, Exam>|ArrayCollection<int, Exam> */
    #[ORM\OneToMany(targetEntity: Exam::class, mappedBy: 'course')]
    private Collection|ArrayCollection $exams;

    /** @var Collection<int, Grade>|ArrayCollection<int, Grade> */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'course')]
    private Collection|ArrayCollection $grades;

    /** @var Collection<int, LearningSessionNote>|ArrayCollection<int, LearningSessionNote> */
    #[ORM\OneToMany(targetEntity: LearningSessionNote::class, mappedBy: 'course')]
    private Collection|ArrayCollection $learningSessionNotes;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->exams = new ArrayCollection();
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
    public function getTeacher(): ?Teacher
    {
        return $this->teacher;
    }
    public function setTeacher(?Teacher $teacher): self
    {
        $this->teacher = $teacher;

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

    public function getContentHtml(): ?string
    {
        return $this->contentHtml;
    }

    public function setContentHtml(?string $contentHtml): self
    {
        $this->contentHtml = $contentHtml;

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
}
