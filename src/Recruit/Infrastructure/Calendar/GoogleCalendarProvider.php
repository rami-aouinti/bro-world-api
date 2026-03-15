<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\Calendar;

use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Service\Interfaces\CalendarProviderInterface;
use Psr\Log\LoggerInterface;

readonly class GoogleCalendarProvider implements CalendarProviderInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {
    }

    public function syncInterviewInvitation(Interview $interview, string $icsContent, bool $isUpdate): void
    {
        $this->logger->info('Google calendar provider is available but not configured.', [
            'interviewId' => $interview->getId(),
            'isUpdate' => $isUpdate,
        ]);
    }
}
