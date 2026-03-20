<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Enum;

use App\General\Domain\Color\Traits\ColorPaletteTrait;

enum QuizLevel: string
{
    use ColorPaletteTrait;
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::EASY;
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EASY => self::normalizeHexColor('#22C55E'),
            self::MEDIUM => self::normalizeHexColor('#F59E0B'),
            self::HARD => self::normalizeHexColor('#EF4444'),
        };
    }
}

