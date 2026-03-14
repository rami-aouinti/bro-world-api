<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\General\Domain\Enum\Locale;
use App\Notification\Application\Service\NotificationPublisher;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\User\Domain\Entity\User;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function array_key_exists;
use function sprintf;
use function str_starts_with;
use function strtolower;

final readonly class RecruitNotificationService
{
    public const string RECRUIT_NOTIFICATION_TYPE = 'recruit_notification';

    private const int IDEMPOTENCE_TTL = 86400;
    private const int DEBOUNCE_TTL = 45;

    /** @var array<string, array<string, array{title: string, description: string}>> */
    private const array TEMPLATES = [
        'application_received' => [
            'fr' => [
                'title' => 'Nouvelle candidature reçue pour "{jobTitle}"',
                'description' => 'Le candidat a postulé au poste "{jobTitle}".',
            ],
            'en' => [
                'title' => 'New application received for "{jobTitle}"',
                'description' => 'A candidate applied to "{jobTitle}".',
            ],
        ],
        'interview_scheduled' => [
            'fr' => [
                'title' => 'Entretien planifié pour "{jobTitle}"',
                'description' => 'Entretien prévu le {interviewDate}.',
            ],
            'en' => [
                'title' => 'Interview scheduled for "{jobTitle}"',
                'description' => 'Interview planned on {interviewDate}.',
            ],
        ],
        'interview_updated' => [
            'fr' => [
                'title' => 'Entretien modifié pour "{jobTitle}"',
                'description' => 'Nouvelle date : {interviewDate}.',
            ],
            'en' => [
                'title' => 'Interview updated for "{jobTitle}"',
                'description' => 'New schedule: {interviewDate}.',
            ],
        ],
        'interview_canceled' => [
            'fr' => [
                'title' => 'Entretien annulé pour "{jobTitle}"',
                'description' => 'L’entretien initialement prévu le {interviewDate} est annulé.',
            ],
            'en' => [
                'title' => 'Interview canceled for "{jobTitle}"',
                'description' => 'The interview scheduled on {interviewDate} has been canceled.',
            ],
        ],
        'status_updated' => [
            'fr' => [
                'title' => 'Statut de candidature mis à jour : {status}',
                'description' => 'Votre candidature pour "{jobTitle}" est maintenant au statut {status}.',
            ],
            'en' => [
                'title' => 'Application status updated: {status}',
                'description' => 'Your application for "{jobTitle}" is now {status}.',
            ],
        ],
        'offer_sent' => [
            'fr' => [
                'title' => 'Offre envoyée pour "{jobTitle}"',
                'description' => 'Une offre vous a été envoyée pour le poste "{jobTitle}".',
            ],
            'en' => [
                'title' => 'Offer sent for "{jobTitle}"',
                'description' => 'An offer has been sent to you for "{jobTitle}".',
            ],
        ],
    ];

    public function __construct(
        private NotificationPublisher $notificationPublisher,
        private CacheInterface $cache,
    ) {
    }

    public function notifyApplicationReceived(Application $application): void
    {
        $actor = $application->getApplicant()->getUser();
        $recipient = $application->getJob()->getOwner();

        if (!$recipient instanceof User) {
            return;
        }

        $this->publishTemplate(
            event: 'application_received',
            actor: $actor,
            recipient: $recipient,
            variables: [
                'jobTitle' => $application->getJob()->getTitle(),
            ],
            idempotenceKey: sprintf('app_received_%s', $application->getId()),
            debounceKey: sprintf('app_received_%s', $application->getId()),
        );
    }

    public function notifyInterviewScheduled(Interview $interview): void
    {
        $this->notifyInterviewEvent('interview_scheduled', $interview);
    }

    public function notifyInterviewUpdated(Interview $interview): void
    {
        $this->notifyInterviewEvent('interview_updated', $interview);
    }

    public function notifyInterviewCanceled(Interview $interview): void
    {
        $this->notifyInterviewEvent('interview_canceled', $interview);
    }

    public function notifyStatusUpdated(Application $application, ApplicationStatus $from, ApplicationStatus $to): void
    {
        $actor = $application->getJob()->getOwner();
        $recipient = $application->getApplicant()->getUser();

        if (!$actor instanceof User || !$this->shouldPublish(
            sprintf('status_%s_%s_%s', $application->getId(), $from->value, $to->value),
            sprintf('status_stream_%s', $application->getId()),
        )) {
            return;
        }

        $this->publishTemplate(
            event: 'status_updated',
            actor: $actor,
            recipient: $recipient,
            variables: [
                'jobTitle' => $application->getJob()->getTitle(),
                'status' => $to->value,
            ],
            idempotenceKey: sprintf('status_tpl_%s_%s', $application->getId(), $to->value),
            debounceKey: sprintf('status_tpl_stream_%s', $application->getId()),
        );
    }

    public function notifyOfferSent(Application $application): void
    {
        $actor = $application->getJob()->getOwner();
        $recipient = $application->getApplicant()->getUser();

        if (!$actor instanceof User) {
            return;
        }

        $this->publishTemplate(
            event: 'offer_sent',
            actor: $actor,
            recipient: $recipient,
            variables: [
                'jobTitle' => $application->getJob()->getTitle(),
            ],
            idempotenceKey: sprintf('offer_sent_%s', $application->getId()),
            debounceKey: sprintf('offer_sent_%s', $application->getId()),
        );
    }

    /** @return array{title: string, description: string} */
    public function renderTemplateSnapshot(string $event, string $locale, array $variables): array
    {
        return $this->renderTemplate($event, $locale, $variables);
    }

    private function notifyInterviewEvent(string $event, Interview $interview): void
    {
        $application = $interview->getApplication();
        $actor = $application->getJob()->getOwner();
        $recipient = $application->getApplicant()->getUser();

        if (!$actor instanceof User) {
            return;
        }

        $this->publishTemplate(
            event: $event,
            actor: $actor,
            recipient: $recipient,
            variables: [
                'jobTitle' => $application->getJob()->getTitle(),
                'interviewDate' => $interview->getScheduledAt()->format(DATE_ATOM),
            ],
            idempotenceKey: sprintf('%s_%s_%s', $event, $application->getId(), $interview->getId()),
            debounceKey: sprintf('%s_stream_%s', $event, $application->getId()),
        );
    }

    /** @param array<string, string> $variables */
    private function publishTemplate(string $event, User $actor, User $recipient, array $variables, string $idempotenceKey, string $debounceKey): void
    {
        if (!$this->shouldPublish($idempotenceKey, $debounceKey)) {
            return;
        }

        $locale = $this->resolveLocale($recipient);
        $content = $this->renderTemplate($event, $locale, $variables);

        $this->notificationPublisher->publish(
            from: $actor,
            recipient: $recipient,
            title: $content['title'],
            type: self::RECRUIT_NOTIFICATION_TYPE,
            description: $content['description'],
        );
    }

    private function shouldPublish(string $idempotenceKey, string $debounceKey): bool
    {
        if (!$this->markIfFirst('recruit_notification_idempotence_' . $idempotenceKey, self::IDEMPOTENCE_TTL)) {
            return false;
        }

        return $this->markIfFirst('recruit_notification_debounce_' . $debounceKey, self::DEBOUNCE_TTL);
    }

    private function markIfFirst(string $key, int $ttl): bool
    {
        $isFirst = false;

        $this->cache->get($key, static function (ItemInterface $item) use (&$isFirst, $ttl): bool {
            $isFirst = true;
            $item->expiresAfter($ttl);

            return true;
        });

        return $isFirst;
    }

    private function resolveLocale(User $user): string
    {
        $locale = strtolower($user->getLocale()->value ?? Locale::getDefault()->value);

        return str_starts_with($locale, 'fr') ? 'fr' : 'en';
    }

    /** @param array<string, string> $variables
     *  @return array{title: string, description: string}
     */
    private function renderTemplate(string $event, string $locale, array $variables): array
    {
        $normalizedLocale = strtolower($locale);
        $template = self::TEMPLATES[$event][(array_key_exists($normalizedLocale, self::TEMPLATES[$event]) ? $normalizedLocale : 'en')];

        $title = $template['title'];
        $description = $template['description'];

        foreach ($variables as $name => $value) {
            $placeholder = '{' . $name . '}';
            $title = str_replace($placeholder, $value, $title);
            $description = str_replace($placeholder, $value, $description);
        }

        return [
            'title' => $title,
            'description' => $description,
        ];
    }
}
