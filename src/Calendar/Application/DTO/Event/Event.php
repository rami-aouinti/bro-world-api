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

    public function __construct()
    {
        $this->startAt = new DateTimeImmutable();
        $this->endAt = $this->startAt;
    }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->setVisited('user'); $this->user = $user; return $this; }
    public function getCalendar(): ?Calendar { return $this->calendar; }
    public function setCalendar(?Calendar $calendar): self { $this->setVisited('calendar'); $this->calendar = $calendar; return $this; }
    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->setVisited('title'); $this->title = $title; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): self { $this->setVisited('description'); $this->description = $description; return $this; }
    public function getStartAt(): DateTimeImmutable { return $this->startAt; }
    public function setStartAt(DateTimeImmutable $startAt): self { $this->setVisited('startAt'); $this->startAt = $startAt; return $this; }
    public function getEndAt(): DateTimeImmutable { return $this->endAt; }
    public function setEndAt(DateTimeImmutable $endAt): self { $this->setVisited('endAt'); $this->endAt = $endAt; return $this; }
    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): self { $this->setVisited('status'); $this->status = $status; return $this; }
    public function getVisibility(): string { return $this->visibility; }
    public function setVisibility(string $visibility): self { $this->setVisited('visibility'); $this->visibility = $visibility; return $this; }

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
        }

        return $this;
    }
}
