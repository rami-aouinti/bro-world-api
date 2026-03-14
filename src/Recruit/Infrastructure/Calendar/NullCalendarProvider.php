<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Calendar;

use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Service\Interfaces\CalendarProviderInterface;

class NullCalendarProvider implements CalendarProviderInterface
{
    public function syncInterviewInvitation(Interview $interview, string $icsContent, bool $isUpdate): void
    {
        // Optional provider disabled.
    }
}
