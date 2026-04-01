<?php

declare(strict_types=1);

namespace App\Game\Domain\Enum;

enum UserGameLevel: string
{
    case EASY = 'easy';
    case MEDIUM = 'medium';
    case HARD = 'hard';
}
