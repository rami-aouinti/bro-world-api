<?php

declare(strict_types=1);

namespace App\Crm\Domain\Entity;

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
#[ORM\Table(name: 'crm')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Crm implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\OneToOne(targetEntity: PlatformApplication::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, unique: true, onDelete: 'CASCADE')]
    private ?PlatformApplication $application = null;

    /** @var Collection<int, Company>|ArrayCollection<int, Company> */
    #[ORM\OneToMany(targetEntity: Company::class, mappedBy: 'crm')]
    private Collection|ArrayCollection $companies;

    /** @throws Throwable */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->companies = new ArrayCollection();
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

    /** @return Collection<int, Company>|ArrayCollection<int, Company> */
    public function getCompanies(): Collection|ArrayCollection
    {
        return $this->companies;
    }
}
