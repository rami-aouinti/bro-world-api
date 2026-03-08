<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Resume;

class ResumeSection
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $description,
    ) {
    }
}
