<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PutCompanyCommand
{
    public function __construct(
        public string $applicationSlug,
        public string $companyId,
        public string $name,
        public ?string $industry,
        public ?string $website,
        public ?string $contactEmail,
        public ?string $phone,
    ) {
    }
}
