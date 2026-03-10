<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\DeleteConversationCommand;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteConversationCommandHandler
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private UserRepository $userRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(DeleteConversationCommand $command): void
    {
        $chatId = $this->conversationRepository->getEntityManager()->getConnection()->transactional(function () use ($command): string {
            $conversation = $this->findParticipantConversation($command->conversationId, $command->actorUserId);
            $chatId = $conversation->getChat()->getId();
            $this->conversationRepository->remove($conversation);

            return $chatId;
        });

        $this->cacheInvalidationService->invalidateConversationCaches($chatId, $command->actorUserId);
    }

    private function findParticipantConversation(string $conversationId, string $actorUserId): Conversation
    {
        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        $actor = $this->userRepository->find($actorUserId);
        if (!$actor instanceof User || !$this->participantRepository->findOneByConversationAndUser($conversation, $actor) instanceof ConversationParticipant) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        return $conversation;
    }
}
