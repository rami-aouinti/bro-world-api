<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum ExperienceLevel: string
{
    case JUNIOR = 'Junior';
    case MID = 'Mid';
    case SENIOR = 'Senior';
    case LEAD = 'Lead';
}

