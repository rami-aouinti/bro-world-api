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
#[ORM\Table(name: 'school_teacher')]
#[ORM\Index(name: 'idx_school_teacher_user_id', columns: ['user_id'])]
#[ORM\UniqueConstraint(name: 'uniq_school_teacher_user', columns: ['user_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Teacher implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass> */
    #[ORM\ManyToMany(targetEntity: SchoolClass::class, inversedBy: 'teachers')]
    #[ORM\JoinTable(name: 'school_class_teacher')]
    private Collection|ArrayCollection $classes;

    /** @var Collection<int, Course>|ArrayCollection<int, Course> */
    #[ORM\OneToMany(targetEntity: Course::class, mappedBy: 'teacher')]
    private Collection|ArrayCollection $courses;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->classes = new ArrayCollection();
        $this->courses = new ArrayCollection();
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

    /**
     * @return Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass>
     */
    public function getClasses(): Collection|ArrayCollection
    {
        return $this->classes;
    }

    /**
     * @return Collection<int, Course>|ArrayCollection<int, Course>
     */
    public function getCourses(): Collection|ArrayCollection
    {
        return $this->courses;
    }
}
