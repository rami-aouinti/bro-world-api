<?php

declare(strict_types=1);

namespace App\Calendar\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CancelEventCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $eventId,
        public ?string $applicationSlug = null,
    ) {
    }
}
