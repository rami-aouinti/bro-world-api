<?php

declare(strict_types=1);

namespace App\Notification\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateNotificationCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $title,
        public string $description,
        public string $type,
        public string $recipientId,
        public ?string $fromId,
    ) {
    }
}
