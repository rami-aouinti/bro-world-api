<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Interview;
use DateInterval;
use DateTimeImmutable;
use DateTimeZone;

use function addcslashes;
use function preg_replace;
use function sprintf;
use function str_replace;

readonly class InterviewCalendarService
{
    public function __construct(private string $defaultTimezone)
    {
    }

    public function generateInvitation(Interview $interview, string $organizerEmail, string $attendeeEmail, bool $isUpdate): string
    {
        $timezone = new DateTimeZone($this->defaultTimezone);
        $startAt = $interview->getScheduledAt()->setTimezone($timezone);
        $endAt = $startAt->add(new DateInterval(sprintf('PT%dM', $interview->getDurationMinutes())));
        $now = new DateTimeImmutable('now', $timezone);

        $summary = sprintf('Entretien - %s', $interview->getApplication()->getJob()->getTitle());
        $description = $interview->getNotes() ?? sprintf('Mode: %s', $interview->getMode()->value);

        $lines = [
            'BEGIN:VCALENDAR',
            'PRODID:-//Bro World//Recruit Interview//FR',
            'VERSION:2.0',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            'UID:' . $interview->getId() . '@bro-world',
            'DTSTAMP:' . $now->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z'),
            'DTSTART;TZID=' . $timezone->getName() . ':' . $startAt->format('Ymd\THis'),
            'DTEND;TZID=' . $timezone->getName() . ':' . $endAt->format('Ymd\THis'),
            'SUMMARY:' . $this->escape($summary),
            'DESCRIPTION:' . $this->escape($description),
            'LOCATION:' . $this->escape($interview->getLocationOrUrl()),
            'ORGANIZER:mailto:' . $organizerEmail,
            'ATTENDEE;CN=' . $this->escape($attendeeEmail) . ';RSVP=TRUE:mailto:' . $attendeeEmail,
            'SEQUENCE:' . ($isUpdate ? '1' : '0'),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];

        return implode("\r\n", $lines) . "\r\n";
    }

    private function escape(string $value): string
    {
        $sanitized = str_replace(["\r\n", "\r", "\n"], '\\n', $value);
        $sanitized = addcslashes($sanitized, ',;');

        return (string) preg_replace('/\s+/', ' ', $sanitized);
    }
}
