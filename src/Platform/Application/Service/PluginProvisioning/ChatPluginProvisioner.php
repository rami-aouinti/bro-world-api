<?php

declare(strict_types=1);

namespace App\Platform\Application\Service\PluginProvisioning;

use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Enum\ConversationParticipantRole;
use App\Chat\Domain\Enum\ConversationType;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Platform\Domain\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ChatPluginProvisioner
{
    public function __construct(
        private ChatRepository $chatRepository,
        private ConversationRepository $conversationRepository,
        private EmployeeRepository $employeeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Application $application): void
    {
        $chat = $this->chatRepository->findOneByApplication($application);
        if (!$chat instanceof Chat) {
            $application->ensureGeneratedSlug();

            $chat = (new Chat())
                ->setApplication($application)
                ->setApplicationSlug($application->getSlug());

            $this->entityManager->persist($chat);
        }

        if ($this->conversationRepository->findOneByChat($chat) instanceof Conversation) {
            return;
        }

        $conversation = (new Conversation())
            ->setChat($chat)
            ->setType(ConversationType::GROUP)
            ->setTitle('General');

        $this->entityManager->persist($conversation);

        $users = $this->employeeRepository->findUsersByApplication($application);
        foreach ($users as $user) {
            $participant = (new ConversationParticipant())
                ->setConversation($conversation)
                ->setUser($user)
                ->setRole(ConversationParticipantRole::MEMBER);

            $this->entityManager->persist($participant);
        }
    }
}
