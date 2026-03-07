<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum Schedule: string
{
    case FULL_TIME = 'Vollzeit';
    case PART_TIME = 'Teilzeit';
    case CONTRACT = 'Contract';
}
