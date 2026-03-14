<?php

declare(strict_types=1);

namespace App\Tests\Integration\Recruit\Application\Service;

use App\Recruit\Application\Service\InterviewCalendarService;
use App\Recruit\Application\Service\InterviewInvitationService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Infrastructure\Calendar\NullCalendarProvider;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\InMemory\InMemoryTransport;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class InterviewInvitationServiceIntegrationTest extends TestCase
{
    public function testSendInvitationIncludesIcsAttachment(): void
    {
        $transport = new InMemoryTransport();
        $mailer = new Mailer($transport);
        $twig = new Environment(new ArrayLoader([
            'invite_created' => '<p>Invitation entretien {{ interview.application.job.title }}</p>',
            'invite_updated' => '<p>Mise à jour entretien {{ interview.application.job.title }}</p>',
        ]));

        $service = new InterviewInvitationService(
            $mailer,
            $twig,
            new InterviewCalendarService('UTC'),
            new NullCalendarProvider(),
            'noreply@example.com',
            'invite_created',
            'invite_updated',
        );

        $service->sendInvitation($this->buildInterview(), false);

        $sent = $transport->getSent();
        self::assertCount(1, $sent);

        $email = $sent[0]->getOriginalMessage();
        self::assertSame('Invitation à un entretien', $email->getSubject());
        self::assertSame(['candidate@example.com'], array_keys($email->getTo()));

        $attachments = $email->getAttachments();
        self::assertCount(1, $attachments);
        self::assertSame('interview.ics', $attachments[0]->getFilename());
        self::assertStringContainsString('BEGIN:VCALENDAR', $attachments[0]->bodyToString());
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
