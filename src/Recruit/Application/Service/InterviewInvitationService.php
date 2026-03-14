<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Service\Interfaces\CalendarProviderInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

readonly class InterviewInvitationService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private InterviewCalendarService $calendarService,
        private CalendarProviderInterface $calendarProvider,
        private string $senderEmail,
        private string $createTemplate,
        private string $updateTemplate,
    ) {
    }

    public function sendInvitation(Interview $interview, bool $isUpdate): void
    {
        $applicantEmail = $interview->getApplication()->getApplicant()->getUser()->getEmail();
        $ownerEmail = $interview->getApplication()->getJob()->getOwner()?->getEmail() ?? $this->senderEmail;
        $icsContent = $this->calendarService->generateInvitation($interview, $ownerEmail, $applicantEmail, $isUpdate);

        $subject = $isUpdate ? 'Mise à jour de votre entretien' : 'Invitation à un entretien';
        $template = $isUpdate ? $this->updateTemplate : $this->createTemplate;
        $body = $this->twig->render($template, [
            'interview' => $interview,
            'isUpdate' => $isUpdate,
        ]);

        $email = (new Email())
            ->from($this->senderEmail)
            ->to($applicantEmail)
            ->subject($subject)
            ->html($body)
            ->attach($icsContent, 'interview.ics', 'text/calendar; charset=UTF-8; method=REQUEST');

        $this->mailer->send($email);
        $this->calendarProvider->syncInterviewInvitation($interview, $icsContent, $isUpdate);
    }
}
