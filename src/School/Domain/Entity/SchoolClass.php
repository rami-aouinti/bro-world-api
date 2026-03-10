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
#[ORM\Table(name: 'school_class')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class SchoolClass implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: School::class, inversedBy: 'classes')]
    #[ORM\JoinColumn(name: 'school_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?School $school = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    /** @var Collection<int, Student>|ArrayCollection<int, Student> */
    #[ORM\OneToMany(targetEntity: Student::class, mappedBy: 'schoolClass')]
    private Collection|ArrayCollection $students;

    /** @var Collection<int, Teacher>|ArrayCollection<int, Teacher> */
    #[ORM\ManyToMany(targetEntity: Teacher::class, mappedBy: 'classes')]
    private Collection|ArrayCollection $teachers;

    /** @var Collection<int, Exam>|ArrayCollection<int, Exam> */
    #[ORM\OneToMany(targetEntity: Exam::class, mappedBy: 'schoolClass')]
    private Collection|ArrayCollection $exams;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->students = new ArrayCollection();
        $this->teachers = new ArrayCollection();
        $this->exams = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getSchool(): ?School
    {
        return $this->school;
    }
    public function setSchool(?School $school): self
    {
        $this->school = $school;

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

    /**
     * @return Collection<int, Student>|ArrayCollection<int, Student>
     */
    public function getStudents(): Collection|ArrayCollection
    {
        return $this->students;
    }
    /**
     * @return Collection<int, Teacher>|ArrayCollection<int, Teacher>
     */
    public function getTeachers(): Collection|ArrayCollection
    {
        return $this->teachers;
    }
    /**
     * @return Collection<int, Exam>|ArrayCollection<int, Exam>
     */
    public function getExams(): Collection|ArrayCollection
    {
        return $this->exams;
    }
}
