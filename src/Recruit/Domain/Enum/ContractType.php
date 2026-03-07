<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum ContractType: string
{
    case CDI = 'CDI';
    case CDD = 'CDD';
    case FREELANCE = 'Freelance';
    case INTERNSHIP = 'Internship';
}
