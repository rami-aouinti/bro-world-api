<?php

declare(strict_types=1);

namespace App\General\Domain\Color\Traits;

use function preg_match;
use function strtoupper;

trait ColorPaletteTrait
{
    protected static function normalizeHexColor(string $color, string $fallback = '#64748B'): string
    {
        $candidate = strtoupper($color);

        return preg_match('/^#[0-9A-F]{6}$/', $candidate) === 1
            ? $candidate
            : strtoupper($fallback);
    }
}
