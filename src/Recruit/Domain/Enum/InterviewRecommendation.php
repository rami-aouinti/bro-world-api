<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum InterviewRecommendation: string
{
    case HIRE = 'hire';
    case NO_HIRE = 'no_hire';
}
