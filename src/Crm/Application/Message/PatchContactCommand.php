<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PatchContactCommand
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $applicationSlug,
        public string $contactId,
        public array $payload,
    ) {
    }
}
