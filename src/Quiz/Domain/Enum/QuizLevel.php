<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Enum;

enum QuizLevel: string
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::EASY;
    }
}
