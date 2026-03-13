<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_applicant')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Applicant implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\OneToOne(targetEntity: Resume::class, inversedBy: 'applicant', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(name: 'resume_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE', unique: true)]
    private ?Resume $resume = null;

    #[ORM\Column(name: 'cover_letter', type: Types::TEXT, options: [
        'default' => '',
    ])]
    private string $coverLetter = '';

    /** @var Collection<int, Application>|ArrayCollection<int, Application> */
    #[ORM\OneToMany(targetEntity: Application::class, mappedBy: 'applicant', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $applications;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->applications = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getUser(): User
    {
        return $this->user;
    }
    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }
    public function getResume(): ?Resume
    {
        return $this->resume;
    }
    public function setResume(?Resume $resume): self
    {
        $this->resume = $resume;

        return $this;
    }
    public function getCoverLetter(): string
    {
        return $this->coverLetter;
    }
    public function setCoverLetter(string $coverLetter): self
    {
        $this->coverLetter = $coverLetter;

        return $this;
    }

    /**
     * @return Collection<int, Application>|ArrayCollection<int, Application>
     */
    public function getApplications(): Collection|ArrayCollection
    {
        return $this->applications;
    }
    public function addApplication(Application $application): self
    {
        if (!$this->applications->contains($application)) {
            $this->applications->add($application);
            $application->setApplicant($this);
        }

        return $this;
    }

    public function removeApplication(Application $application): self
    {
        $this->applications->removeElement($application);

        return $this;
    }
}
