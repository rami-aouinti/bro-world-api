<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Entity;

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
use Symfony\Component\Serializer\Attribute\Groups;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'calendar')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Calendar implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Calendar', 'Calendar.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    #[Groups(['Calendar', 'Calendar.title'])]
    private string $title = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['Calendar', 'Calendar.user'])]
    private ?User $user = null;

    /** @var Collection<int, Event>|ArrayCollection<int, Event> */
    #[ORM\OneToMany(targetEntity: Event::class, mappedBy: 'calendar')]
    #[Groups(['Calendar', 'Calendar.events'])]
    private Collection|ArrayCollection $events;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->events = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
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

    /** @return Collection<int, Event>|ArrayCollection<int, Event> */
    public function getEvents(): Collection|ArrayCollection
    {
        return $this->events;
    }

    public function addEvent(Event $event): self
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
            $event->setCalendar($this);
        }

        return $this;
    }

    public function removeEvent(Event $event): self
    {
        if ($this->events->removeElement($event) && $event->getCalendar() === $this) {
            $event->setCalendar(null);
        }

        return $this;
    }
}
