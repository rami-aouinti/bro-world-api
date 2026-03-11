<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

final readonly class SlugBuilderService
{
    public function buildSlug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
    }
}
