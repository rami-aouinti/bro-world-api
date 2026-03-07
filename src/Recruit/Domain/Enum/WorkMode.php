<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum WorkMode: string
{
    case ONSITE = 'Onsite';
    case REMOTE = 'Remote';
    case HYBRID = 'Hybrid';
}
