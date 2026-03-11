<?php

declare(strict_types=1);

namespace App\Calendar\Application\Message;

use App\Calendar\Domain\Enum\EventVisibility;
use App\General\Domain\Message\Interfaces\MessageHighInterface;
use DateTimeImmutable;

final readonly class PatchEventCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $eventId,
        public ?string $title,
        public ?string $description,
        public ?DateTimeImmutable $startAt,
        public ?DateTimeImmutable $endAt,
        public ?EventVisibility $visibility,
        public ?string $location,
        public ?bool $isAllDay,
        public ?string $timezone,
        public ?string $url,
        public ?string $color,
        public ?string $backgroundColor,
        public ?string $borderColor,
        public ?string $textColor,
        public ?string $organizerName,
        public ?string $organizerEmail,
        /** @var array<int|string, mixed>|null */
        public ?array $attendees,
        public ?string $rrule,
        /** @var array<int|string, mixed>|null */
        public ?array $recurrenceExceptions,
        public ?DateTimeImmutable $recurrenceEndAt,
        public ?int $recurrenceCount,
        /** @var array<int|string, mixed>|null */
        public ?array $reminders,
        /** @var array<int|string, mixed>|null */
        public ?array $metadata,
        public ?string $applicationSlug = null,
    ) {
    }
}
