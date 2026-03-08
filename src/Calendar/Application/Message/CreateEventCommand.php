<?php

declare(strict_types=1);

namespace App\Calendar\Application\Message;

use App\Calendar\Domain\Enum\EventStatus;
use App\General\Domain\Message\Interfaces\MessageHighInterface;
use DateTimeImmutable;

final readonly class CreateEventCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $title,
        public string $description,
        public DateTimeImmutable $startAt,
        public DateTimeImmutable $endAt,
        public EventStatus $status,
        public ?string $location,
        public ?string $applicationSlug = null,
    ) {
    }
}
