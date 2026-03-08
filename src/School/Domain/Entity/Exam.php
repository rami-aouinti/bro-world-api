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
#[ORM\Table(name: 'school_exam')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Exam implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'exams')]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\ManyToOne(targetEntity: Teacher::class)]
    #[ORM\JoinColumn(name: 'teacher_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Teacher $teacher = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    private string $title = '';

    /** @var Collection<int, Grade>|ArrayCollection<int, Grade> */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'exam')]
    private Collection|ArrayCollection $grades;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->grades = new ArrayCollection();
    }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getSchoolClass(): ?SchoolClass { return $this->schoolClass; }
    public function setSchoolClass(?SchoolClass $schoolClass): self { $this->schoolClass = $schoolClass; return $this; }
    public function getTeacher(): ?Teacher { return $this->teacher; }
    public function setTeacher(?Teacher $teacher): self { $this->teacher = $teacher; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }

    /** @return Collection<int, Grade>|ArrayCollection<int, Grade> */
    public function getGrades(): Collection|ArrayCollection { return $this->grades; }
}
