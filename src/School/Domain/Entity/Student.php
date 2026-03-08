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
#[ORM\Table(name: 'school_student')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Student implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: SchoolClass::class, inversedBy: 'students')]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?SchoolClass $schoolClass = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    /** @var Collection<int, Grade>|ArrayCollection<int, Grade> */
    #[ORM\OneToMany(targetEntity: Grade::class, mappedBy: 'student')]
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
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, Grade>|ArrayCollection<int, Grade> */
    public function getGrades(): Collection|ArrayCollection { return $this->grades; }
}
