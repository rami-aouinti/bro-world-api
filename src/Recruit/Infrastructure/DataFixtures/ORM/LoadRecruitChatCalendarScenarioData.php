<?php

declare(strict_types=1);

namespace App\Recruit\Infrastructure\DataFixtures\ORM;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\ChatMessageReaction;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Plugin;
use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadRecruitChatCalendarScenarioData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<string, Chat>
     */
    private array $ensuredChats = [];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        /** @var Plugin $chatPlugin */
        $chatPlugin = $this->getReference('Plugin-CRM-Assistant', Plugin::class);
        /** @var Plugin $calendarPlugin */
        $calendarPlugin = $this->getReference('Plugin-Analytics-Booster', Plugin::class);

        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);

        $recruitApplications = [
            $this->getReference('Application-recruit-talent-hub', PlatformApplication::class),
            $this->getReference('Application-recruit-hiring-pipeline', PlatformApplication::class),
            $this->getReference('Application-recruit-interview-desk', PlatformApplication::class),
        ];

        foreach ($recruitApplications as $application) {
            if (!$application instanceof PlatformApplication) {
                continue;
            }

            $this->ensurePluginAttached($manager, $application, $chatPlugin);
            $this->ensurePluginAttached($manager, $application, $calendarPlugin);

            $chat = $this->ensureChat($manager, $application);
            $calendar = $this->ensureCalendar($manager, $application);

            $this->ensureApplicationCalendarEvents($manager, $application, $calendar);
            $this->ensureJohnRootPrivateEvents($manager, $application, $calendar, $johnRoot);

            if ($application->getTitle() === 'Recruit Talent Hub') {
                $this->createDiscussionConversationScenario($manager, $chat);
                $this->createJohnRootConversationScenario($manager, $chat, $calendar);
            }
        }

        $this->createJohnRootPrivateDirectMessageScenarios($manager);
        $this->createDedicatedDirectConversationFixture($manager);

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }

    private function createJohnRootPrivateDirectMessageScenarios(ObjectManager $manager): void
    {
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);
        /** @var User $johnUser */
        $johnUser = $this->getReference('User-john-user', User::class);
        /** @var User $johnApi */
        $johnApi = $this->getReference('User-john-api', User::class);

        $privateConversationSetups = [
            [
                'application' => $this->getReference('Application-crm-pipeline-pro', PlatformApplication::class),
                'partner' => $johnAdmin,
                'firstMessage' => 'Hello John Admin, on peut échanger en privé sur les prochaines étapes ?',
                'replyMessage' => 'Oui, je te confirme le plan et je garde cette conversation en direct.',
                'rootReaction' => '🤝',
                'partnerReaction' => '✅',
            ],
            [
                'application' => $this->getReference('Application-shop-orders-watch', PlatformApplication::class),
                'partner' => $johnUser,
                'firstMessage' => 'Salut John User, je te partage ici les points sensibles côté commandes.',
                'replyMessage' => 'Parfait, je m’en occupe et je te fais un retour rapidement.',
                'rootReaction' => '👍',
                'partnerReaction' => '👀',
            ],
            [
                'application' => $this->getReference('Application-school-course-flow', PlatformApplication::class),
                'partner' => $johnApi,
                'firstMessage' => 'Hello John API, on valide ensemble la synchro de ce soir en privé ?',
                'replyMessage' => 'Oui, je lance la synchro et je confirme dès que c’est terminé.',
                'rootReaction' => '🚀',
                'partnerReaction' => '👌',
            ],
        ];

        foreach ($privateConversationSetups as $index => $setup) {
            /** @var PlatformApplication $application */
            $application = $setup['application'];
            /** @var User $partner */
            $partner = $setup['partner'];

            $chat = $this->ensureChat($manager, $application);
            $conversation = $this->ensureConversation($manager, $chat);

            $this->ensureParticipant($manager, $conversation, $johnRoot);
            $this->ensureParticipant($manager, $conversation, $partner);

            $firstMessage = $this->ensureMessage(
                $manager,
                $conversation,
                $johnRoot,
                $setup['firstMessage'],
                []
            );

            $replyMessage = $this->ensureMessage(
                $manager,
                $conversation,
                $partner,
                $setup['replyMessage'],
                []
            );

            $this->ensureReaction($manager, $firstMessage, $partner, $setup['partnerReaction']);
            $this->ensureReaction($manager, $replyMessage, $johnRoot, $setup['rootReaction']);

            $this->addReference('Recruit-Conversation-john-root-private-' . ($index + 1), $conversation);
        }
    }

    private function createDedicatedDirectConversationFixture(ObjectManager $manager): void
    {
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);
        /** @var PlatformApplication $application */
        $application = $this->getReference('Application-crm-pipeline-pro', PlatformApplication::class);

        $chat = $this->ensureChat($manager, $application);

        $conversation = (new Conversation())
            ->setChat($chat);

        $manager->persist($conversation);

        $this->ensureParticipant($manager, $conversation, $johnRoot);
        $this->ensureParticipant($manager, $conversation, $johnAdmin);

        $this->addReference('Recruit-Conversation-direct-john-root-john-admin', $conversation);
    }

    private function ensurePluginAttached(ObjectManager $manager, PlatformApplication $application, Plugin $plugin): void
    {
        $existing = $manager->getRepository(ApplicationPlugin::class)->findOneBy([
            'application' => $application,
            'plugin' => $plugin,
        ]);

        if ($existing instanceof ApplicationPlugin) {
            return;
        }

        $applicationPlugin = (new ApplicationPlugin())
            ->setApplication($application)
            ->setPlugin($plugin);

        $application->addApplicationPlugin($applicationPlugin);
        $manager->persist($applicationPlugin);
    }

    private function ensureChat(ObjectManager $manager, PlatformApplication $application): Chat
    {
        $application->ensureGeneratedSlug();
        $slug = $application->getSlug();

        if (isset($this->ensuredChats[$slug])) {
            return $this->ensuredChats[$slug];
        }

        $existing = $manager->getRepository(Chat::class)->findOneBy([
            'application' => $application,
        ]);

        if ($existing instanceof Chat) {
            $this->ensuredChats[$slug] = $existing;

            return $existing;
        }

        $chat = (new Chat())
            ->setApplication($application)
            ->setApplicationSlug($slug);

        $manager->persist($chat);

        $this->ensuredChats[$slug] = $chat;

        return $chat;
    }

    private function ensureCalendar(ObjectManager $manager, PlatformApplication $application): Calendar
    {
        $existing = $manager->getRepository(Calendar::class)->findOneBy([
            'application' => $application,
        ]);

        if ($existing instanceof Calendar) {
            return $existing;
        }

        $calendar = (new Calendar())
            ->setTitle('Recruit calendar - ' . $application->getTitle())
            ->setApplication($application)
            ->setUser($application->getUser());

        $manager->persist($calendar);

        return $calendar;
    }

    private function ensureApplicationCalendarEvents(ObjectManager $manager, PlatformApplication $application, Calendar $calendar): void
    {
        $this->ensureEvent(
            $manager,
            $calendar,
            $application->getUser() ?? $calendar->getUser(),
            'Recruit event - ' . $application->getTitle(),
            2,
            'Scheduled event for recruit application workflow.'
        );

        $this->ensureEvent(
            $manager,
            $calendar,
            $application->getUser() ?? $calendar->getUser(),
            'Recruit onboarding - ' . $application->getTitle(),
            4,
            'Onboarding checkpoint for candidates and hiring team.'
        );

        $this->ensureEvent(
            $manager,
            $calendar,
            $application->getUser() ?? $calendar->getUser(),
            'Recruit review - ' . $application->getTitle(),
            7,
            'Weekly review for interviews and offers.'
        );
    }

    private function createDiscussionConversationScenario(ObjectManager $manager, Chat $chat): void
    {
        /** @var User $john */
        $john = $this->getReference('User-john', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);

        $conversation = $this->ensureConversation($manager, $chat);
        $this->ensureParticipant($manager, $conversation, $john);
        $this->ensureParticipant($manager, $conversation, $johnAdmin);

        $introMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $john,
            'Salut, je voulais faire un point rapide sur les candidatures de cette semaine.',
            [
                'attachmentType' => 'note',
                'name' => 'weekly-review.txt',
            ]
        );

        $replyMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $johnAdmin,
            'Parfait, partage-moi les profils les plus urgents et je priorise les retours.',
            []
        );

        $this->ensureReaction($manager, $replyMessage, $john, '👍');
        $this->ensureReaction($manager, $introMessage, $johnAdmin, '✅');
    }

    private function createJohnRootConversationScenario(ObjectManager $manager, Chat $chat, Calendar $calendar): void
    {
        /** @var RecruitApplication $johnRootRecruitApplication */
        $johnRootRecruitApplication = $this->getReference('Recruit-Application-john-root-on-other-owner-waiting', RecruitApplication::class);
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $johnUser */
        $johnUser = $this->getReference('User-john-user', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);

        $johnRootRecruitApplication->setStatus(ApplicationStatus::WAITING);
        $otherOwner = $johnRootRecruitApplication->getJob()->getOwner();

        if (!$otherOwner instanceof User) {
            return;
        }

        $conversation = $this->ensureConversation($manager, $chat);
        $this->ensureParticipant($manager, $conversation, $johnRoot);

        if ($johnRoot->getId() !== $otherOwner->getId()) {
            $this->ensureParticipant($manager, $conversation, $otherOwner);
        }

        if ($johnRoot->getId() !== $johnUser->getId()) {
            $this->ensureParticipant($manager, $conversation, $johnUser);
        }

        if ($johnRoot->getId() !== $johnAdmin->getId()) {
            $this->ensureParticipant($manager, $conversation, $johnAdmin);
        }

        $johnRootMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $johnRoot,
            'Bonjour, je confirme mon intérêt pour ce poste et mes disponibilités.',
            []
        );

        $ownerReplyMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $otherOwner,
            'Parfait, merci John. Je reviens vers vous rapidement pour la suite.',
            []
        );

        $johnUserMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $johnUser,
            'Salut John, je peux partager un retour sur le processus de recrutement.',
            []
        );

        $this->ensureMessage(
            $manager,
            $conversation,
            $johnAdmin,
            'Je valide le créneau de revue, on se cale demain matin.',
            []
        );

        $this->ensureMessage(
            $manager,
            $conversation,
            $johnRoot,
            'Super, je prépare aussi une synthèse des entretiens techniques.',
            []
        );

        $this->ensureReaction($manager, $ownerReplyMessage, $johnRoot, '✅');
        $this->ensureReaction($manager, $ownerReplyMessage, $johnAdmin, '👍');
        $this->ensureReaction($manager, $johnUserMessage, $johnAdmin, '👀');
        $this->ensureReaction($manager, $johnRootMessage, $otherOwner, '👏');

        $event = $this->ensureJohnRootScenarioEvent($manager, $calendar, $johnRoot);

        $this->addReference('Recruit-Conversation-john-root-scenario', $conversation);
        $this->addReference('Recruit-Message-john-root-scenario-from-john-root', $johnRootMessage);
        $this->addReference('Recruit-Message-john-root-scenario-from-owner', $ownerReplyMessage);
        $this->addReference('Recruit-Event-john-root-scenario', $event);
    }

    private function ensureJohnRootPrivateEvents(
        ObjectManager $manager,
        PlatformApplication $application,
        Calendar $calendar,
        User $johnRoot
    ): void {
        $prefix = 'John Root private event - ' . $application->getSlug();

        for ($dayOffset = 1; $dayOffset <= 8; $dayOffset++) {
            $this->ensureEvent(
                $manager,
                $calendar,
                $johnRoot,
                sprintf('%s #%d', $prefix, $dayOffset),
                10 + $dayOffset,
                sprintf('Private planning event #%d for john-root on %s.', $dayOffset, $application->getTitle()),
            );
        }
    }

    private function ensureJohnRootScenarioEvent(ObjectManager $manager, Calendar $calendar, User $johnRoot): Event
    {
        return $this->ensureEvent(
            $manager,
            $calendar,
            $johnRoot,
            'Recruit event - john-root scenario',
            3,
            'Event dédié au scénario fixtures john-root pour tests fonctionnels.'
        );
    }

    private function ensureEvent(
        ObjectManager $manager,
        Calendar $calendar,
        ?User $owner,
        string $title,
        int $daysInFuture,
        string $description
    ): Event {
        $existing = $manager->getRepository(Event::class)->findOneBy([
            'calendar' => $calendar,
            'title' => $title,
        ]);

        if ($existing instanceof Event) {
            return $existing;
        }

        $startAt = (new DateTimeImmutable())->modify(sprintf('+%d day', $daysInFuture));

        $event = (new Event())
            ->setTitle($title)
            ->setDescription($description)
            ->setLocation('Recruit HQ')
            ->setStartAt($startAt)
            ->setEndAt($startAt->modify('+1 hour'))
            ->setTimezone('Europe/Paris')
            ->setOrganizerName('Recruit Team')
            ->setOrganizerEmail('recruit@example.com')
            ->setAttendees([
                [
                    'name' => 'Hiring Manager',
                    'email' => 'hiring.manager@example.com',
                ],
            ])
            ->setReminders([
                [
                    'method' => 'email',
                    'minutesBefore' => 30,
                ],
            ])
            ->setMetadata([
                'source' => 'fixtures',
                'calendarId' => $calendar->getId(),
                'title' => $title,
            ])
            ->setStatus(EventStatus::CONFIRMED)
            ->setVisibility(EventVisibility::PRIVATE)
            ->setUser($owner ?? $calendar->getUser())
            ->setCalendar($calendar);

        $manager->persist($event);

        return $event;
    }

    private function ensureConversation(ObjectManager $manager, Chat $chat): Conversation
    {
        static $conversationByChat = [];

        $chatKey = $chat->getId();

        if (isset($conversationByChat[$chatKey]) && $conversationByChat[$chatKey] instanceof Conversation) {
            return $conversationByChat[$chatKey];
        }

        $conversation = $manager->getRepository(Conversation::class)->findOneBy([
            'chat' => $chat,
        ]);

        if ($conversation instanceof Conversation) {
            $conversationByChat[$chatKey] = $conversation;

            return $conversation;
        }

        $conversation = (new Conversation())
            ->setChat($chat);

        $manager->persist($conversation);
        $conversationByChat[$chatKey] = $conversation;

        return $conversation;
    }

    private function ensureParticipant(ObjectManager $manager, Conversation $conversation, User $user): void
    {
        static $participantsByConversationAndUser = [];

        $conversationKey = (string)($conversation->getId() ?? 'new-' . spl_object_id($conversation));
        $userKey = (string)($user->getId() ?? 'new-' . spl_object_id($user));
        $participantKey = $conversationKey . '::' . $userKey;

        if (isset($participantsByConversationAndUser[$participantKey])) {
            return;
        }

        $existing = $manager->getRepository(ConversationParticipant::class)->findOneBy([
            'conversation' => $conversation,
            'user' => $user,
        ]);

        if ($existing instanceof ConversationParticipant) {
            $participantsByConversationAndUser[$participantKey] = true;

            return;
        }

        $participant = (new ConversationParticipant())
            ->setConversation($conversation)
            ->setUser($user);

        $manager->persist($participant);
        $participantsByConversationAndUser[$participantKey] = true;
    }

    /**
     * @param array<int|string, mixed> $attachments
     */
    private function ensureMessage(
        ObjectManager $manager,
        Conversation $conversation,
        User $sender,
        string $content,
        array $attachments
    ): ChatMessage {
        $existing = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'sender' => $sender,
            'content' => $content,
        ]);

        if ($existing instanceof ChatMessage) {
            return $existing;
        }

        $message = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($sender)
            ->setContent($content)
            ->setAttachments($attachments)
            ->setRead(true)
            ->setReadAt(new DateTimeImmutable());

        $manager->persist($message);

        return $message;
    }

    private function ensureReaction(ObjectManager $manager, ChatMessage $message, User $user, string $emoji): void
    {
        $existing = $manager->getRepository(ChatMessageReaction::class)->findOneBy([
            'message' => $message,
            'user' => $user,
            'reaction' => $emoji,
        ]);

        if ($existing instanceof ChatMessageReaction) {
            return;
        }

        $reaction = (new ChatMessageReaction())
            ->setMessage($message)
            ->setUser($user)
            ->setReaction($emoji);

        $manager->persist($reaction);
    }
}
