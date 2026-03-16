<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class CreateCompanyCommand
{
    public function __construct(
        public string $id,
        public string $applicationSlug,
        public string $name,
        public ?string $industry,
        public ?string $website,
        public ?string $contactEmail,
        public ?string $phone,
    ) {
    }
}
