<?php

declare(strict_types=1);

namespace App\General\Application\Message;

use function uniqid;

abstract class EntityMutationMessage
{
    /**
     * @var array<string, mixed>
     */
    public array $context;

    public readonly string $eventId;

    public function __construct(
        public readonly string $entityType,
        public readonly string $entityId,
        ?string $eventId = null,
        array $context = [],
    ) {
        $this->eventId = $eventId ?? uniqid('evt_', true);
        $this->context = $context;
    }
}
