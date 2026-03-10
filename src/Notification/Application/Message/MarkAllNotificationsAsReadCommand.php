<?php

declare(strict_types=1);

namespace App\Notification\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class MarkAllNotificationsAsReadCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $actorUserId,
    ) {
    }
}
