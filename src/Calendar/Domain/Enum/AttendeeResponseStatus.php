<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Enum;

enum AttendeeResponseStatus: string
{
    case ACCEPTED = 'accepted';
    case DECLINED = 'declined';
    case TENTATIVE = 'tentative';
    case NEEDS_ACTION = 'needs_action';
}
