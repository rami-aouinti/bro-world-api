<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\InterviewCalendarService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class InterviewCalendarServiceTest extends TestCase
{
    public function testGenerateInvitationProducesIcsPayload(): void
    {
        $service = new InterviewCalendarService('Europe/Paris');
        $interview = $this->buildInterview();

        $ics = $service->generateInvitation($interview, 'owner@example.com', 'candidate@example.com', false);

        self::assertStringContainsString('BEGIN:VCALENDAR', $ics);
        self::assertStringContainsString('METHOD:REQUEST', $ics);
        self::assertStringContainsString('UID:' . $interview->getId() . '@bro-world', $ics);
        self::assertStringContainsString('DTSTART;TZID=Europe/Paris:20301201T150000', $ics);
        self::assertStringContainsString('DTEND;TZID=Europe/Paris:20301201T154500', $ics);
        self::assertStringContainsString('SUMMARY:Entretien - Senior Backend Engineer', $ics);
        self::assertStringContainsString('ATTENDEE;CN=candidate@example.com;RSVP=TRUE:mailto:candidate@example.com', $ics);
        self::assertStringContainsString('SEQUENCE:0', $ics);
    }

    public function testGenerateInvitationUsesSequenceOnUpdate(): void
    {
        $service = new InterviewCalendarService('UTC');
        $interview = $this->buildInterview();

        $ics = $service->generateInvitation($interview, 'owner@example.com', 'candidate@example.com', true);

        self::assertStringContainsString('SEQUENCE:1', $ics);
        self::assertStringContainsString('DTSTART;TZID=UTC:20301201T140000', $ics);
    }

    private function buildInterview(): Interview
    {
        $owner = (new User())
            ->setEmail('owner@example.com')
            ->setUsername('owner')
            ->setFirstName('Owner')
            ->setLastName('User');

        $candidate = (new User())
            ->setEmail('candidate@example.com')
            ->setUsername('candidate')
            ->setFirstName('Candidate')
            ->setLastName('User');

        $job = (new Job())
            ->setOwner($owner)
            ->setTitle('Senior Backend Engineer')
            ->ensureGeneratedSlug();

        $applicant = (new Applicant())->setUser($candidate);
        $application = (new Application())->setApplicant($applicant)->setJob($job);

        return (new Interview())
            ->setApplication($application)
            ->setScheduledAt(new DateTimeImmutable('2030-12-01 14:00:00+00:00'))
            ->setDurationMinutes(45)
            ->setMode('visio')
            ->setLocationOrUrl('https://meet.example/room')
            ->setInterviewerIds(['u-1'])
            ->setNotes('Préparer un exercice API');
    }
}
