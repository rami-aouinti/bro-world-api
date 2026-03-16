<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PutBillingCommand
{
    public function __construct(
        public string $applicationSlug,
        public string $billingId,
        public string $companyId,
        public string $label,
        public float $amount,
        public string $currency,
        public string $status,
        public ?string $dueAt,
    ) {
    }
}
