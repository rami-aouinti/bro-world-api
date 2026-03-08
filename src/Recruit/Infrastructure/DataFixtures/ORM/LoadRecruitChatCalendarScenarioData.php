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
    #[Override]
    public function load(ObjectManager $manager): void
    {
        /** @var Plugin $chatPlugin */
        $chatPlugin = $this->getReference('Plugin-CRM-Assistant', Plugin::class);
        /** @var Plugin $calendarPlugin */
        $calendarPlugin = $this->getReference('Plugin-Analytics-Booster', Plugin::class);

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
            $this->ensureEvent($manager, $application, $calendar);

            if ($application->getTitle() === 'Recruit Talent Hub') {
                $this->createDiscussionConversationScenario($manager, $chat);
                $this->createJohnRootConversationScenario($manager, $chat, $calendar);
            }
        }

        $manager->flush();
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
        $existing = $manager->getRepository(Chat::class)->findOneBy([
            'application' => $application,
        ]);

        if ($existing instanceof Chat) {
            return $existing;
        }

        $application->ensureGeneratedSlug();

        $chat = (new Chat())
            ->setApplication($application)
            ->setApplicationSlug($application->getSlug());

        $manager->persist($chat);

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

    private function ensureEvent(ObjectManager $manager, PlatformApplication $application, Calendar $calendar): void
    {
        $existing = $manager->getRepository(Event::class)->findOneBy([
            'calendar' => $calendar,
            'title' => 'Recruit event - ' . $application->getTitle(),
        ]);

        if ($existing instanceof Event) {
            return;
        }

        $startAt = (new DateTimeImmutable())->modify('+2 day');

        $event = (new Event())
            ->setTitle('Recruit event - ' . $application->getTitle())
            ->setDescription('Scheduled event for recruit application workflow.')
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
                'applicationSlug' => $application->getSlug(),
            ])
            ->setStatus(EventStatus::CONFIRMED)
            ->setVisibility(EventVisibility::PRIVATE)
            ->setUser($application->getUser())
            ->setCalendar($calendar);

        $manager->persist($event);
    }

    private function ensureJohnRootEvent(ObjectManager $manager, Calendar $calendar, User $johnRoot): Event
    {
        $existing = $manager->getRepository(Event::class)->findOneBy([
            'calendar' => $calendar,
            'title' => 'Recruit event - john-root scenario',
        ]);

        if ($existing instanceof Event) {
            return $existing;
        }

        $startAt = (new DateTimeImmutable())->modify('+3 day');

        $event = (new Event())
            ->setTitle('Recruit event - john-root scenario')
            ->setDescription('Event dédié au scénario fixtures john-root pour tests fonctionnels.')
            ->setLocation('Recruit HQ - Room Root')
            ->setStartAt($startAt)
            ->setEndAt($startAt->modify('+45 minutes'))
            ->setTimezone('Europe/Paris')
            ->setOrganizerName('John Root')
            ->setOrganizerEmail('john.root@example.com')
            ->setAttendees([
                [
                    'name' => 'John Root',
                    'email' => 'john.root@example.com',
                ],
            ])
            ->setReminders([
                [
                    'method' => 'email',
                    'minutesBefore' => 15,
                ],
            ])
            ->setMetadata([
                'source' => 'fixtures',
                'scenario' => 'john-root',
            ])
            ->setStatus(EventStatus::CONFIRMED)
            ->setVisibility(EventVisibility::PRIVATE)
            ->setUser($johnRoot)
            ->setCalendar($calendar);

        $manager->persist($event);

        return $event;
    }

    private function createDiscussionConversationScenario(ObjectManager $manager, Chat $chat): void
    {
        /** @var RecruitApplication $discussionApplication */
        $discussionApplication = $this->getReference('Recruit-Application-john-admin-on-other-owner-discussion', RecruitApplication::class);

        if ($discussionApplication->getStatus() !== ApplicationStatus::DISCUSSION) {
            return;
        }

        $conversation = $this->ensureConversation($manager, $chat);

        $owner = $discussionApplication->getJob()->getOwner();
        $applicant = $discussionApplication->getApplicant()->getUser();

        if (!$owner instanceof User || !$applicant instanceof User) {
            return;
        }

        $this->ensureParticipant($manager, $conversation, $owner);

        if ($owner->getId() !== $applicant->getId()) {
            $this->ensureParticipant($manager, $conversation, $applicant);
        }

        $introMessage = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'content' => 'Bonjour, merci pour votre candidature. Discutons de votre profil.',
        ]);

        if (!$introMessage instanceof ChatMessage) {
            $introMessage = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($owner)
                ->setContent('Bonjour, merci pour votre candidature. Discutons de votre profil.')
                ->setAttachments([]);
            $manager->persist($introMessage);
        }

        $replyMessage = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'content' => 'Merci, je suis disponible pour échanger quand vous voulez.',
        ]);

        if (!$replyMessage instanceof ChatMessage) {
            $replyMessage = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($applicant)
                ->setContent('Merci, je suis disponible pour échanger quand vous voulez.')
                ->setAttachments([])
                ->setReadAt(new DateTimeImmutable());
            $manager->persist($replyMessage);
        }

        $reaction = $manager->getRepository(ChatMessageReaction::class)->findOneBy([
            'message' => $replyMessage,
            'user' => $owner,
            'reaction' => '👍',
        ]);

        if ($reaction instanceof ChatMessageReaction) {
            return;
        }

        $reaction = (new ChatMessageReaction())
            ->setMessage($replyMessage)
            ->setUser($owner)
            ->setReaction('👍');

        $manager->persist($reaction);
    }

    private function createJohnRootConversationScenario(
        ObjectManager $manager,
        Chat $chat,
        Calendar $calendar,
    ): void {
        /** @var RecruitApplication $johnRootRecruitApplication */
        $johnRootRecruitApplication = $this->getReference('Recruit-Application-john-root-on-other-owner-waiting', RecruitApplication::class);

        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $johnUser */
        $johnUser = $this->getReference('User-john-user', User::class);
        /** @var User $johnAdmin */
        $johnAdmin = $this->getReference('User-john-admin', User::class);

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

        $johnRootMessage = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'content' => 'Bonjour, je confirme mon intérêt pour ce poste et mes disponibilités.',
        ]);

        if (!$johnRootMessage instanceof ChatMessage) {
            $johnRootMessage = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($johnRoot)
                ->setContent('Bonjour, je confirme mon intérêt pour ce poste et mes disponibilités.')
                ->setAttachments([])
                ->setReadAt(new DateTimeImmutable());
            $manager->persist($johnRootMessage);
        }

        $ownerReplyMessage = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'content' => 'Parfait, merci John. Je reviens vers vous rapidement pour la suite.',
        ]);

        if (!$ownerReplyMessage instanceof ChatMessage) {
            $ownerReplyMessage = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($otherOwner)
                ->setContent('Parfait, merci John. Je reviens vers vous rapidement pour la suite.')
                ->setAttachments([]);
            $manager->persist($ownerReplyMessage);
        }

        $reaction = $manager->getRepository(ChatMessageReaction::class)->findOneBy([
            'message' => $ownerReplyMessage,
            'user' => $johnRoot,
            'reaction' => '✅',
        ]);

        if (!$reaction instanceof ChatMessageReaction) {
            $reaction = (new ChatMessageReaction())
                ->setMessage($ownerReplyMessage)
                ->setUser($johnRoot)
                ->setReaction('✅');
            $manager->persist($reaction);
        }

        $johnUserMessage = $manager->getRepository(ChatMessage::class)->findOneBy([
            'conversation' => $conversation,
            'content' => 'Salut John, je peux partager un retour sur le processus de recrutement.',
        ]);

        if (!$johnUserMessage instanceof ChatMessage) {
            $johnUserMessage = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($johnUser)
                ->setContent('Salut John, je peux partager un retour sur le processus de recrutement.')
                ->setAttachments([])
                ->setReadAt(new DateTimeImmutable());
            $manager->persist($johnUserMessage);
        }

        $johnAdminReaction = $manager->getRepository(ChatMessageReaction::class)->findOneBy([
            'message' => $johnUserMessage,
            'user' => $johnAdmin,
            'reaction' => '👀',
        ]);

        if (!$johnAdminReaction instanceof ChatMessageReaction) {
            $johnAdminReaction = (new ChatMessageReaction())
                ->setMessage($johnUserMessage)
                ->setUser($johnAdmin)
                ->setReaction('👀');
            $manager->persist($johnAdminReaction);
        }

        $event = $this->ensureJohnRootEvent($manager, $calendar, $johnRoot);

        $this->addReference('Recruit-Conversation-john-root-scenario', $conversation);
        $this->addReference('Recruit-Message-john-root-scenario-from-john-root', $johnRootMessage);
        $this->addReference('Recruit-Message-john-root-scenario-from-owner', $ownerReplyMessage);
        $this->addReference('Recruit-Event-john-root-scenario', $event);
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

        $conversationKey = (string) ($conversation->getId() ?? 'new-' . spl_object_id($conversation));
        $userKey = (string) ($user->getId() ?? 'new-' . spl_object_id($user));
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

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }
}
