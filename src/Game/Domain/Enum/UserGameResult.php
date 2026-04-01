<?php

declare(strict_types=1);

namespace App\Game\Domain\Enum;

enum UserGameResult: string
{
    case WIN = 'win';
    case LOSE = 'lose';
}
