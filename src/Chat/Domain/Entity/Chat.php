<?php

declare(strict_types=1);

namespace App\Chat\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'chat')]
#[ORM\UniqueConstraint(name: 'uq_chat_application_slug', columns: ['application_slug'])]
#[ORM\Index(name: 'idx_chat_application_slug', columns: ['application_slug'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Chat implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Application $application;

    #[ORM\Column(name: 'application_slug', type: 'string', length: 100)]
    private string $applicationSlug;

    /**
     * @var Collection<int, Conversation>
     */
    #[ORM\OneToMany(targetEntity: Conversation::class, mappedBy: 'chat')]
    private Collection $conversations;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->conversations = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
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

    public function getApplicationSlug(): string
    {
        return $this->applicationSlug;
    }

    public function setApplicationSlug(string $applicationSlug): self
    {
        $this->applicationSlug = $applicationSlug;

        return $this;
    }

    /**
     * @return Collection<int, Conversation>
     */
    public function getConversations(): Collection
    {
        return $this->conversations;
    }
}
