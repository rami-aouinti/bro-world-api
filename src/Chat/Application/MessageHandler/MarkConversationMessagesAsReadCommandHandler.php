<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\MarkConversationMessagesAsReadCommand;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class MarkConversationMessagesAsReadCommandHandler
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(MarkConversationMessagesAsReadCommand $command): void
    {
        $result = $this->findParticipantConversation($command->conversationId, $command->actorUserId);
        if (null === $result) {
            return;
        }

        [$conversation, $participant] = $result;

        $participant->setLastReadMessageAt(new \DateTimeImmutable());
        $this->participantRepository->save($participant);
        $this->cacheInvalidationService->invalidateConversationCaches($conversation->getChat()->getId(), $command->actorUserId);
    }

    /**
     * @return array{Conversation, ConversationParticipant}|null
     */
    private function findParticipantConversation(string $conversationId, string $actorUserId): ?array
    {
        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            return null;
        }

        $participant = $this->participantRepository->findOneBy([
            'conversation' => $conversation,
            'user' => $actorUserId,
        ]);
        if (!$participant instanceof ConversationParticipant) {
            return null;
        }

        return [$conversation, $participant];
    }
}
