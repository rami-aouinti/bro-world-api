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
                $this->createDiscussionConversationScenario($manager, $application, $chat);
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
            ->setStartAt($startAt)
            ->setEndAt($startAt->modify('+1 hour'))
            ->setStatus(EventStatus::CONFIRMED)
            ->setVisibility(EventVisibility::PRIVATE)
            ->setUser($application->getUser())
            ->setCalendar($calendar);

        $manager->persist($event);
    }

    private function createDiscussionConversationScenario(ObjectManager $manager, PlatformApplication $application, Chat $chat): void
    {
        /** @var RecruitApplication $discussionApplication */
        $discussionApplication = $this->getReference('Recruit-Application-john-admin-on-other-owner-discussion', RecruitApplication::class);

        if ($discussionApplication->getStatus() !== ApplicationStatus::DISCUSSION) {
            return;
        }

        $conversation = $manager->getRepository(Conversation::class)->findOneBy([
            'chat' => $chat,
            'applicationSlug' => $application->getSlug(),
        ]);

        if (!$conversation instanceof Conversation) {
            $conversation = (new Conversation())
                ->setChat($chat)
                ->setApplicationSlug($application->getSlug());

            $manager->persist($conversation);
        }

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

    private function ensureParticipant(ObjectManager $manager, Conversation $conversation, User $user): void
    {
        $existing = $manager->getRepository(ConversationParticipant::class)->findOneBy([
            'conversation' => $conversation,
            'user' => $user,
        ]);

        if ($existing instanceof ConversationParticipant) {
            return;
        }

        $participant = (new ConversationParticipant())
            ->setConversation($conversation)
            ->setUser($user);

        $manager->persist($participant);
    }

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }
}
