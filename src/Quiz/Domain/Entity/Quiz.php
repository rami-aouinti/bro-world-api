<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Entity;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'quiz')]
class Quiz implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /**
     * @var Collection<int, QuizQuestion>
     */
    #[ORM\OneToMany(targetEntity: QuizQuestion::class, mappedBy: 'quiz', cascade: ['remove'])]
    private Collection $questions;

    #[ORM\ManyToOne(targetEntity: Configuration::class)]
    #[ORM\JoinColumn(name: 'configuration_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Configuration $configuration = null;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->questions = new ArrayCollection();
    }
    #[Override] public function getId(): string
    {
        return $this->id->toString();
    }
    public function getApplication(): Application
    {
        return $this->application;
    }
    public function setApplication(Application $application): self
    {
        $this->application = $application;

        return $this;
    }
    public function getOwner(): User
    {
        return $this->owner;
    }
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
    /**
     * @return Collection<int, QuizQuestion>
     */ public function getQuestions(): Collection
    {
        return $this->questions;
    }
    public function getConfiguration(): ?Configuration
    {
        return $this->configuration;
    }
    public function setConfiguration(?Configuration $configuration): self
    {
        $this->configuration = $configuration;

        return $this;
    }
}
