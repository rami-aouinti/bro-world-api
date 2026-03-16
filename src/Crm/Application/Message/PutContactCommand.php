<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

final readonly class PutContactCommand
{
    public function __construct(
        public string $applicationSlug,
        public string $contactId,
        public string $firstName,
        public string $lastName,
        public ?string $email,
        public ?string $phone,
        public ?string $jobTitle,
        public ?string $city,
        public int $score,
        public ?string $companyId,
    ) {
    }
}
