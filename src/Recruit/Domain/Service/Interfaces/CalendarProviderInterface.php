<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Service\Interfaces;

use App\Recruit\Domain\Entity\Interview;

interface CalendarProviderInterface
{
    public function syncInterviewInvitation(Interview $interview, string $icsContent, bool $isUpdate): void;
}
