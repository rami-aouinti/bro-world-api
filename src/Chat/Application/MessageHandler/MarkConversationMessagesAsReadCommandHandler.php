<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\MarkConversationMessagesAsReadCommand;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
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
        [$conversation, $participant] = $this->findParticipantConversation($command->conversationId, $command->actorUserId);

        $participant->setLastReadMessageAt(new \DateTimeImmutable());
        $this->participantRepository->save($participant);
        $this->cacheInvalidationService->invalidateConversationCaches($conversation->getChat()->getId(), $command->actorUserId);
    }

    /**
     * @return array{Conversation, ConversationParticipant}
     */
    private function findParticipantConversation(string $conversationId, string $actorUserId): array
    {
        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        $participant = $this->participantRepository->findOneBy([
            'conversation' => $conversation,
            'user' => $actorUserId,
        ]);
        if (!$participant instanceof ConversationParticipant) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        return [$conversation, $participant];
    }
}
