<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'recruit')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Recruit implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\OneToOne(targetEntity: PlatformApplication::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?PlatformApplication $application = null;

    /**
     * @var Collection<int, Job>|ArrayCollection<int, Job>
     */
    #[ORM\OneToMany(targetEntity: Job::class, mappedBy: 'recruit')]
    private Collection|ArrayCollection $jobs;

    /** @throws Throwable */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->jobs = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
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

    /** @return Collection<int, Job>|ArrayCollection<int, Job> */
    public function getJobs(): Collection|ArrayCollection
    {
        return $this->jobs;
    }
}

