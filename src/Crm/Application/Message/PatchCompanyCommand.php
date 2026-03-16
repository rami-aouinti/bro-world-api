<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PatchCompanyCommand
{
    public function __construct(
        public string $applicationSlug,
        public string $companyId,
        public ?string $name,
        public bool $hasName,
        public ?string $industry,
        public bool $hasIndustry,
        public ?string $website,
        public bool $hasWebsite,
        public ?string $contactEmail,
        public bool $hasContactEmail,
        public ?string $phone,
        public bool $hasPhone,
    ) {
    }
}
