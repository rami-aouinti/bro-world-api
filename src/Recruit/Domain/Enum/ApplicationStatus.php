<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Enum;

enum ApplicationStatus: string
{
    case WAITING = 'WAITING';
    case SCREENING = 'SCREENING';
    case INTERVIEW_PLANNED = 'INTERVIEW_PLANNED';
    case INTERVIEW_DONE = 'INTERVIEW_DONE';
    case OFFER_SENT = 'OFFER_SENT';
    case HIRED = 'HIRED';
    case REJECTED = 'REJECTED';
}
