<?php

declare(strict_types=1);

namespace App\Chat\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class DeleteConversationCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $actorUserId,
        public string $conversationId,
    ) {
    }
}
