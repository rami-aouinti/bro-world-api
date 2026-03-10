<?php

declare(strict_types=1);

namespace App\Calendar\Application\DTO\Event;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Domain\Entity\Event as Entity;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\General\Application\DTO\RestDto;
use App\General\Application\Validator\Constraints as AppAssert;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Override;

class Event extends RestDto
{
    #[AppAssert\EntityReferenceExists(entityClass: User::class)]
    protected ?User $user = null;

    #[AppAssert\EntityReferenceExists(entityClass: Calendar::class)]
    protected ?Calendar $calendar = null;

    protected string $title = '';
    protected string $description = '';
    protected DateTimeImmutable $startAt;
    protected DateTimeImmutable $endAt;
    protected string $status = EventStatus::CONFIRMED->value;
    protected string $visibility = EventVisibility::PRIVATE->value;
    protected ?string $location = null;
    protected bool $isAllDay = false;
    protected ?string $timezone = null;
    protected bool $isCancelled = false;
    protected ?string $url = null;
    protected ?string $color = null;
    protected ?string $backgroundColor = null;
    protected ?string $borderColor = null;
    protected ?string $textColor = null;
    protected ?string $organizerName = null;
    protected ?string $organizerEmail = null;
    protected ?array $attendees = null;
    protected ?string $rrule = null;
    protected ?array $recurrenceExceptions = null;
    protected ?DateTimeImmutable $recurrenceEndAt = null;
    protected ?int $recurrenceCount = null;
    protected ?array $reminders = null;
    protected ?array $metadata = null;

    public function __construct()
    {
        $this->startAt = new DateTimeImmutable();
        $this->endAt = $this->startAt;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->setVisited('user');
        $this->user = $user;

        return $this;
    }
    public function getCalendar(): ?Calendar
    {
        return $this->calendar;
    }
    public function setCalendar(?Calendar $calendar): self
    {
        $this->setVisited('calendar');
        $this->calendar = $calendar;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

        return $this;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function setDescription(string $description): self
    {
        $this->setVisited('description');
        $this->description = $description;

        return $this;
    }
    public function getStartAt(): DateTimeImmutable
    {
        return $this->startAt;
    }
    public function setStartAt(DateTimeImmutable $startAt): self
    {
        $this->setVisited('startAt');
        $this->startAt = $startAt;

        return $this;
    }
    public function getEndAt(): DateTimeImmutable
    {
        return $this->endAt;
    }
    public function setEndAt(DateTimeImmutable $endAt): self
    {
        $this->setVisited('endAt');
        $this->endAt = $endAt;

        return $this;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->setVisited('status');
        $this->status = $status;

        return $this;
    }
    public function getVisibility(): string
    {
        return $this->visibility;
    }
    public function setVisibility(string $visibility): self
    {
        $this->setVisited('visibility');
        $this->visibility = $visibility;

        return $this;
    }
    public function getLocation(): ?string
    {
        return $this->location;
    }
    public function setLocation(?string $location): self
    {
        $this->setVisited('location');
        $this->location = $location;

        return $this;
    }
    public function isAllDay(): bool
    {
        return $this->isAllDay;
    }
    public function setIsAllDay(bool $isAllDay): self
    {
        $this->setVisited('isAllDay');
        $this->isAllDay = $isAllDay;

        return $this;
    }
    public function getTimezone(): ?string
    {
        return $this->timezone;
    }
    public function setTimezone(?string $timezone): self
    {
        $this->setVisited('timezone');
        $this->timezone = $timezone;

        return $this;
    }
    public function isCancelled(): bool
    {
        return $this->isCancelled;
    }
    public function setIsCancelled(bool $isCancelled): self
    {
        $this->setVisited('isCancelled');
        $this->isCancelled = $isCancelled;

        return $this;
    }
    public function getUrl(): ?string
    {
        return $this->url;
    }
    public function setUrl(?string $url): self
    {
        $this->setVisited('url');
        $this->url = $url;

        return $this;
    }
    public function getColor(): ?string
    {
        return $this->color;
    }
    public function setColor(?string $color): self
    {
        $this->setVisited('color');
        $this->color = $color;

        return $this;
    }
    public function getBackgroundColor(): ?string
    {
        return $this->backgroundColor;
    }
    public function setBackgroundColor(?string $backgroundColor): self
    {
        $this->setVisited('backgroundColor');
        $this->backgroundColor = $backgroundColor;

        return $this;
    }
    public function getBorderColor(): ?string
    {
        return $this->borderColor;
    }
    public function setBorderColor(?string $borderColor): self
    {
        $this->setVisited('borderColor');
        $this->borderColor = $borderColor;

        return $this;
    }
    public function getTextColor(): ?string
    {
        return $this->textColor;
    }
    public function setTextColor(?string $textColor): self
    {
        $this->setVisited('textColor');
        $this->textColor = $textColor;

        return $this;
    }
    public function getOrganizerName(): ?string
    {
        return $this->organizerName;
    }
    public function setOrganizerName(?string $organizerName): self
    {
        $this->setVisited('organizerName');
        $this->organizerName = $organizerName;

        return $this;
    }
    public function getOrganizerEmail(): ?string
    {
        return $this->organizerEmail;
    }
    public function setOrganizerEmail(?string $organizerEmail): self
    {
        $this->setVisited('organizerEmail');
        $this->organizerEmail = $organizerEmail;

        return $this;
    }
    public function getAttendees(): ?array
    {
        return $this->attendees;
    }
    public function setAttendees(?array $attendees): self
    {
        $this->setVisited('attendees');
        $this->attendees = $attendees;

        return $this;
    }
    public function getRrule(): ?string
    {
        return $this->rrule;
    }
    public function setRrule(?string $rrule): self
    {
        $this->setVisited('rrule');
        $this->rrule = $rrule;

        return $this;
    }
    public function getRecurrenceExceptions(): ?array
    {
        return $this->recurrenceExceptions;
    }
    public function setRecurrenceExceptions(?array $recurrenceExceptions): self
    {
        $this->setVisited('recurrenceExceptions');
        $this->recurrenceExceptions = $recurrenceExceptions;

        return $this;
    }
    public function getRecurrenceEndAt(): ?DateTimeImmutable
    {
        return $this->recurrenceEndAt;
    }
    public function setRecurrenceEndAt(?DateTimeImmutable $recurrenceEndAt): self
    {
        $this->setVisited('recurrenceEndAt');
        $this->recurrenceEndAt = $recurrenceEndAt;

        return $this;
    }
    public function getRecurrenceCount(): ?int
    {
        return $this->recurrenceCount;
    }
    public function setRecurrenceCount(?int $recurrenceCount): self
    {
        $this->setVisited('recurrenceCount');
        $this->recurrenceCount = $recurrenceCount;

        return $this;
    }
    public function getReminders(): ?array
    {
        return $this->reminders;
    }
    public function setReminders(?array $reminders): self
    {
        $this->setVisited('reminders');
        $this->reminders = $reminders;

        return $this;
    }
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }
    public function setMetadata(?array $metadata): self
    {
        $this->setVisited('metadata');
        $this->metadata = $metadata;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->user = $entity->getUser();
            $this->calendar = $entity->getCalendar();
            $this->title = $entity->getTitle();
            $this->description = $entity->getDescription();
            $this->startAt = $entity->getStartAt();
            $this->endAt = $entity->getEndAt();
            $this->status = $entity->getStatusValue();
            $this->visibility = $entity->getVisibilityValue();
            $this->location = $entity->getLocation();
            $this->isAllDay = $entity->isAllDay();
            $this->timezone = $entity->getTimezone();
            $this->isCancelled = $entity->isCancelled();
            $this->url = $entity->getUrl();
            $this->color = $entity->getColor();
            $this->backgroundColor = $entity->getBackgroundColor();
            $this->borderColor = $entity->getBorderColor();
            $this->textColor = $entity->getTextColor();
            $this->organizerName = $entity->getOrganizerName();
            $this->organizerEmail = $entity->getOrganizerEmail();
            $this->attendees = $entity->getAttendees();
            $this->rrule = $entity->getRrule();
            $this->recurrenceExceptions = $entity->getRecurrenceExceptions();
            $this->recurrenceEndAt = $entity->getRecurrenceEndAt();
            $this->recurrenceCount = $entity->getRecurrenceCount();
            $this->reminders = $entity->getReminders();
            $this->metadata = $entity->getMetadata();
        }

        return $this;
    }
}
