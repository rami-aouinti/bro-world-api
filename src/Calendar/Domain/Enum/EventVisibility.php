<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Enum;

enum EventVisibility: string
{
    case PRIVATE = 'private';
    case PUBLIC = 'public';
}
