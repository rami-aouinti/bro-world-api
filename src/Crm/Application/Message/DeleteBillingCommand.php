<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class DeleteBillingCommand
{
    public function __construct(
        public string $applicationSlug,
        public string $billingId,
    ) {
    }
}
