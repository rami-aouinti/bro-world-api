<?php

declare(strict_types=1);

namespace App\Game\Domain\Enum;

enum UserGameStatus: string
{
    case STARTED = 'STARTED';
    case FINISHED = 'FINISHED';
}
