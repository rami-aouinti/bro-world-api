<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\Recruit\Domain\Entity\Application;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class ApplicationSlaReminderService
{
    public function __construct(
        private MailerServiceInterface $mailerService,
        private HttpClientInterface $httpClient,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private string $appSenderEmail,
        private string $slackWebhook = '',
    ) {
    }

    /**
     * @param list<Application> $applications
     */
    public function sendReminders(array $applications): void
    {
        foreach ($applications as $application) {
            $email = $application->getJob()->getOwner()?->getEmail();
            if (is_string($email) && $email !== '') {
                $this->mailerService->sendMail(
                    title: sprintf('[SLA] Candidature %s en dépassement', $application->getId()),
                    from: $this->appSenderEmail,
                    to: $email,
                    body: $this->buildMessage($application),
                );
            }

            if ($this->slackWebhook !== '') {
                $this->httpClient->request('POST', $this->slackWebhook, [
                    'json' => [
                        'text' => sprintf(
                            ':warning: SLA dépassée pour candidature `%s` (%s) sur le poste `%s`.',
                            $application->getId(),
                            $application->getStatus()->value,
                            $application->getJob()->getTitle(),
                        ),
                    ],
                ]);
            }
        }
    }

    private function buildMessage(Application $application): string
    {
        return sprintf(
            "La candidature %s est en dépassement de SLA.\nStatut: %s\nPoste: %s\nDernière mise à jour: %s",
            $application->getId(),
            $application->getStatus()->value,
            $application->getJob()->getTitle(),
            $application->getUpdatedAt()?->format(DATE_ATOM) ?? 'n/a',
        );
    }
}
