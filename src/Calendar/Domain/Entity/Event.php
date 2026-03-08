<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Entity;

use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Throwable;

#[ORM\Entity]
#[ORM\Table(
    name: 'calendar_event',
    indexes: [
        new ORM\Index(name: 'idx_calendar_event_start_at', columns: ['start_at']),
        new ORM\Index(name: 'idx_calendar_event_end_at', columns: ['end_at']),
        new ORM\Index(name: 'idx_calendar_event_status', columns: ['status']),
        new ORM\Index(name: 'idx_calendar_event_visibility', columns: ['visibility']),
        new ORM\Index(name: 'idx_calendar_event_user_id', columns: ['user_id']),
        new ORM\Index(name: 'idx_calendar_event_calendar_id', columns: ['calendar_id']),
    ]
)]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Event implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Event', 'Event.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    #[Groups(['Event', 'Event.title'])]
    private string $title = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, options: ['default' => ''])]
    #[Groups(['Event', 'Event.description'])]
    private string $description = '';

    #[ORM\Column(name: 'start_at', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['Event', 'Event.startAt'])]
    private DateTimeImmutable $startAt;

    #[ORM\Column(name: 'end_at', type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['Event', 'Event.endAt'])]
    private DateTimeImmutable $endAt;

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: EventStatus::class, options: ['default' => EventStatus::CONFIRMED->value])]
    #[Groups(['Event', 'Event.status'])]
    private EventStatus $status = EventStatus::CONFIRMED;

    #[ORM\Column(name: 'visibility', type: Types::STRING, length: 25, enumType: EventVisibility::class, options: ['default' => EventVisibility::PRIVATE->value])]
    #[Groups(['Event', 'Event.visibility'])]
    private EventVisibility $visibility = EventVisibility::PRIVATE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['Event', 'Event.user'])]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Calendar::class, inversedBy: 'events')]
    #[ORM\JoinColumn(name: 'calendar_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['Event', 'Event.calendar'])]
    private ?Calendar $calendar = null;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->startAt = new DateTimeImmutable();
        $this->endAt = $this->startAt;
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }
    public function getStartAt(): DateTimeImmutable { return $this->startAt; }
    public function setStartAt(DateTimeImmutable $startAt): self { $this->startAt = $startAt; return $this; }
    public function getEndAt(): DateTimeImmutable { return $this->endAt; }
    public function setEndAt(DateTimeImmutable $endAt): self { $this->endAt = $endAt; return $this; }
    public function getStatus(): EventStatus { return $this->status; }
    public function getStatusValue(): string { return $this->status->value; }
    public function setStatus(EventStatus|string $status): self { $this->status = $status instanceof EventStatus ? $status : EventStatus::from($status); return $this; }
    public function getVisibility(): EventVisibility { return $this->visibility; }
    public function getVisibilityValue(): string { return $this->visibility->value; }
    public function setVisibility(EventVisibility|string $visibility): self { $this->visibility = $visibility instanceof EventVisibility ? $visibility : EventVisibility::from($visibility); return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }
    public function getCalendar(): ?Calendar { return $this->calendar; }
    public function setCalendar(?Calendar $calendar): self { $this->calendar = $calendar; return $this; }
}
