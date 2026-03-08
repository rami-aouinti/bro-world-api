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
#[ORM\Table(name: 'school_teacher')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Teacher implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    /** @var Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass> */
    #[ORM\ManyToMany(targetEntity: SchoolClass::class, inversedBy: 'teachers')]
    #[ORM\JoinTable(name: 'school_class_teacher')]
    private Collection|ArrayCollection $classes;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->classes = new ArrayCollection();
    }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }

    /** @return Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass> */
    public function getClasses(): Collection|ArrayCollection { return $this->classes; }
}
