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
use App\Chat\Domain\Enum\ChatReactionType;
use App\General\Domain\Rest\UuidHelper;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Platform\Domain\Entity\Plugin;
use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

final class LoadRecruitChatCalendarScenarioData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'chat-crm-pipeline-pro' => '91000000-0000-1000-8000-000000000001',
        'conversation-direct-john-root-john-admin' => '91000000-0000-1000-8000-000000000002',
        'conversation-john-root-scenario' => '91000000-0000-1000-8000-000000000003',
        'message-john-root-scenario-from-john-root' => '91000000-0000-1000-8000-000000000004',
        'message-john-root-scenario-from-owner' => '91000000-0000-1000-8000-000000000005',
        'reaction-john-root-scenario-owner-on-root-message' => '91000000-0000-1000-8000-000000000006',
    ];

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

        $chatEnabledApplications = $this->getApplicationsByPlugin($manager, $chatPlugin);
        $calendarEnabledApplications = $this->getApplicationsByPlugin($manager, $calendarPlugin);

        foreach ($chatEnabledApplications as $application) {
            $chat = $this->ensureChat($manager, $application);
            $this->ensureApplicationChatScenario($manager, $application, $chat);

            if ($application->getTitle() === 'Recruit Talent Hub') {
                $calendar = $this->ensureCalendar($manager, $application);
                $this->createDiscussionConversationScenario($manager, $chat);
                $this->createJohnRootConversationScenario($manager, $chat, $calendar);
            }
        }

        foreach ($calendarEnabledApplications as $application) {
            $calendar = $this->ensureCalendar($manager, $application);
            $this->ensureApplicationCalendarEvents($manager, $application, $calendar);
            $this->ensureJohnRootPrivateEvents($manager, $application, $calendar, $johnRoot);
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

    public static function getUuidByKey(string $key): string
    {
        return self::$uuids[$key];
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
                'rootReaction' => 'love',
                'partnerReaction' => 'love',
            ],
            [
                'application' => $this->getReference('Application-shop-orders-watch', PlatformApplication::class),
                'partner' => $johnUser,
                'firstMessage' => 'Salut John User, je te partage ici les points sensibles côté commandes.',
                'replyMessage' => 'Parfait, je m’en occupe et je te fais un retour rapidement.',
                'rootReaction' => 'like',
                'partnerReaction' => 'wow',
            ],
            [
                'application' => $this->getReference('Application-school-course-flow', PlatformApplication::class),
                'partner' => $johnApi,
                'firstMessage' => 'Hello John API, on valide ensemble la synchro de ce soir en privé ?',
                'replyMessage' => 'Oui, je lance la synchro et je confirme dès que c’est terminé.',
                'rootReaction' => 'love',
                'partnerReaction' => 'like',
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

    /**
     * @throws Throwable
     */
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

        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['conversation-direct-john-root-john-admin']), $conversation);

        $manager->persist($conversation);

        $this->ensureParticipant($manager, $conversation, $johnRoot);
        $this->ensureParticipant($manager, $conversation, $johnAdmin);

        $this->addReference('Recruit-Conversation-direct-john-root-john-admin', $conversation);
    }

    /**
     * @return list<PlatformApplication>
     */
    private function getApplicationsByPlugin(ObjectManager $manager, Plugin $plugin): array
    {
        $results = $manager->getRepository(PlatformApplication::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicationPlugins', 'applicationPlugin')
            ->andWhere('applicationPlugin.plugin = :plugin')
            ->setParameter('plugin', $plugin)
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($results, static fn (mixed $item): bool => $item instanceof PlatformApplication));
    }

    /**
     * @throws Throwable
     */
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

        if ($slug === 'crm-pipeline-pro') {
            PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['chat-crm-pipeline-pro']), $chat);
        }

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

        $this->ensureReaction($manager, $replyMessage, $john, 'like');
        $this->ensureReaction($manager, $introMessage, $johnAdmin, 'love');
    }

    private function ensureApplicationChatScenario(ObjectManager $manager, PlatformApplication $application, Chat $chat): void
    {
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);

        $conversation = $this->ensureConversation($manager, $chat);

        $this->ensureParticipant($manager, $conversation, $johnRoot);
        if ($johnRoot->getId() !== $johnAdmin->getId()) {
            $this->ensureParticipant($manager, $conversation, $johnAdmin);
        }

        $introMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $johnRoot,
            sprintf('Chat plugin activé pour %s, je lance un fil de suivi.', $application->getTitle()),
            []
        );

        $replyMessage = $this->ensureMessage(
            $manager,
            $conversation,
            $johnAdmin,
            sprintf('Parfait, je confirme la modération pour %s.', $application->getTitle()),
            []
        );

        $this->ensureReaction($manager, $introMessage, $johnAdmin, 'like');
        $this->ensureReaction($manager, $replyMessage, $johnRoot, 'love');
    }

    /**
     * @throws Throwable
     */
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
        if ($chat->getApplicationSlug() === 'crm-pipeline-pro') {
            PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['conversation-john-root-scenario']), $conversation);
        }
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

        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['message-john-root-scenario-from-john-root']), $johnRootMessage);
        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['message-john-root-scenario-from-owner']), $ownerReplyMessage);

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

        $this->ensureReaction($manager, $ownerReplyMessage, $johnRoot, 'love');
        $this->ensureReaction($manager, $ownerReplyMessage, $johnAdmin, 'like');
        $this->ensureReaction($manager, $johnUserMessage, $johnAdmin, 'wow');
        $this->ensureReaction(
            $manager,
            $johnRootMessage,
            $otherOwner,
            'laugh',
            self::$uuids['reaction-john-root-scenario-owner-on-root-message']
        );

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
                    'status' => 'accepted',
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

    /**
     * @throws Throwable
     */
    private function ensureReaction(ObjectManager $manager, ChatMessage $message, User $user, string $reaction, ?string $forcedUuid = null): void
    {
        $existing = $manager->getRepository(ChatMessageReaction::class)->findOneBy([
            'message' => $message,
            'user' => $user,
            'reaction' => $reaction,
        ]);

        if ($existing instanceof ChatMessageReaction) {
            return;
        }

        $reaction = (new ChatMessageReaction())
            ->setMessage($message)
            ->setUser($user)
            ->setReaction(ChatReactionType::from($reaction));

        if ($forcedUuid !== null) {
            PhpUnitUtil::setProperty('id', UuidHelper::fromString($forcedUuid), $reaction);
        }

        $manager->persist($reaction);
    }
}
