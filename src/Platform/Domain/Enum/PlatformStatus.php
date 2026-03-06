<?php

declare(strict_types=1);

namespace App\Platform\Domain\Enum;

/**
 * @package App\Platform
 */
enum PlatformStatus: string
{
    case ACTIVE = 'active';
    case MAINTENANCE = 'maintenance';
    case DISABLED = 'disabled';
}
