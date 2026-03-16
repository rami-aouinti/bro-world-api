<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PatchBillingCommand
{
    /** @param array<string,mixed> $payload */
    public function __construct(
        public string $applicationSlug,
        public string $billingId,
        public array $payload,
    ) {
    }
}
