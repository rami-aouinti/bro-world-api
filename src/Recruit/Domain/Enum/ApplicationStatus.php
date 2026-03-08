<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum ApplicationStatus: string
{
    case WAITING = 'WAITING';
    case REVIEWING = 'REVIEWING';
    case INTERVIEW = 'INTERVIEW';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
