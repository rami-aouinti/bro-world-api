<?php

declare(strict_types=1);

namespace App\Crm\Domain\Enum;

enum ProjectStatus: string
{
    case PLANNED = 'planned';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
}

