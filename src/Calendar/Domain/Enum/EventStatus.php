<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Enum;

enum EventStatus: string
{
    case TENTATIVE = 'tentative';
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
}
