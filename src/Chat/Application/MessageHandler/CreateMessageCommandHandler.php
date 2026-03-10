<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\CreateMessageCommand;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateMessageCommandHandler
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private UserRepository $userRepository,
        private ChatMessageRepository $messageRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(CreateMessageCommand $command): void
    {
        $chatId = $this->messageRepository->getEntityManager()->getConnection()->transactional(function () use ($command): string {
            $actor = $this->userRepository->find($command->actorUserId);
            if (!$actor instanceof User) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
            }

            $conversation = $this->findParticipantConversation($command->conversationId, $actor);

            $message = (new ChatMessage())
                ->setConversation($conversation)
                ->setSender($actor)
                ->setContent($command->content)
                ->setAttachments([])
                ->setRead(false);

            $this->messageRepository->save($message);

            return $conversation->getChat()->getId();
        });

        $this->cacheInvalidationService->invalidateConversationCaches($chatId, $command->actorUserId);
    }

    private function findParticipantConversation(string $conversationId, User $actor): Conversation
    {
        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        if (!$this->participantRepository->findOneByConversationAndUser($conversation, $actor) instanceof ConversationParticipant) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        return $conversation;
    }
}
