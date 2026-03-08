<?php

declare(strict_types=1);

namespace App\Calendar\Application\Message;

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
        public ?string $applicationSlug = null,
    ) {
    }
}
