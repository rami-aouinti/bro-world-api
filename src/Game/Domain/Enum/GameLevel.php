<?php

declare(strict_types=1);

namespace App\Game\Domain\Enum;

enum GameLevel: string
{
    case BEGINNER = 'BEGINNER';
    case INTERMEDIATE = 'INTERMEDIATE';
    case ADVANCED = 'ADVANCED';
    case EXPERT = 'EXPERT';
}
