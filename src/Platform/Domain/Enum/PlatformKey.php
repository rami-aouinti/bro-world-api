<?php

declare(strict_types=1);

namespace App\Platform\Domain\Enum;

/**
 * @package App\Platform
 */
enum PlatformKey: string
{
    case CRM = 'crm';
    case SCHOOL = 'school';
    case SHOP = 'shop';
    case RECRUIT = 'recruit';
}
