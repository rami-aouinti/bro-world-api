<?php

declare(strict_types=1);

namespace App\Quiz\Domain\Enum;

enum QuizCategory: string
{
    case GENERAL = 'general';
    case BACKEND = 'backend';
    case FRONTEND = 'frontend';
    case DEVOPS = 'devops';
    case ONBOARDING = 'onboarding';

    public static function fromString(string $value): self
    {
        return self::tryFrom(strtolower(trim($value))) ?? self::GENERAL;
    }
}
