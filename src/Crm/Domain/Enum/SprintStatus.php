<?php

declare(strict_types=1);

namespace App\Crm\Domain\Enum;

enum SprintStatus: string
{
    case PLANNED = 'planned';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}
