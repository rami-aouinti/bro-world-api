<?php

declare(strict_types=1);

namespace App\School\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'school')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class School implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\OneToOne(targetEntity: PlatformApplication::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?PlatformApplication $application = null;

    /** @var Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass> */
    #[ORM\OneToMany(targetEntity: SchoolClass::class, mappedBy: 'school')]
    private Collection|ArrayCollection $classes;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->classes = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
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
    public function getApplication(): ?PlatformApplication
    {
        return $this->application;
    }
    public function setApplication(?PlatformApplication $application): self
    {
        $this->application = $application;

        return $this;
    }

    /**
     * @return Collection<int, SchoolClass>|ArrayCollection<int, SchoolClass>
     */
    public function getClasses(): Collection|ArrayCollection
    {
        return $this->classes;
    }
}
