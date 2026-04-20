<?php

declare(strict_types=1);

namespace App\School\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'school_student')]
#[ORM\Index(name: 'idx_school_student_class_id', columns: ['class_id'])]
#[ORM\Index(name: 'idx_school_student_user_id', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_school_student_user_class', columns: ['user_id', 'class_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Student implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'students')]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?SchoolClass $schoolClass = null;

    /** @var Collection<int, Grade>|ArrayCollection<int, Grade> */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'student')]
    private Collection|ArrayCollection $grades;

    /** @var Collection<int, LearningSessionNote>|ArrayCollection<int, LearningSessionNote> */
    #[ORM\OneToMany(targetEntity: LearningSessionNote::class, mappedBy: 'student')]
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
    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }
    public function getDisplayName(): string
    {
        if (!$this->user instanceof User) {
            return '';
        }

        return trim($this->user->getFirstName() . ' ' . $this->user->getLastName());
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

    /**
     * @return Collection<int, Grade>|ArrayCollection<int, Grade>
     */
    public function getGrades(): Collection|ArrayCollection
    {
        return $this->grades;
    }

    /**
     * @return Collection<int, LearningSessionNote>|ArrayCollection<int, LearningSessionNote>
     */
    public function getLearningSessionNotes(): Collection|ArrayCollection
    {
        return $this->learningSessionNotes;
    }
}
