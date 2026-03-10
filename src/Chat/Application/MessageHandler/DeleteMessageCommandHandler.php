<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\DeleteMessageCommand;
use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\General\Application\Service\CacheInvalidationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteMessageCommandHandler
{
    public function __construct(
        private ChatMessageRepository $messageRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(DeleteMessageCommand $command): void
    {
        $chatId = $this->messageRepository->getEntityManager()->getConnection()->transactional(function () use ($command): string {
            $message = $this->findOwnMessage($command->messageId, $command->actorUserId);
            $chatId = $message->getConversation()->getChat()->getId();
            $this->messageRepository->remove($message);

            return $chatId;
        });

        $this->cacheInvalidationService->invalidateConversationCaches($chatId, $command->actorUserId);
    }

    private function findOwnMessage(string $messageId, string $actorUserId): ChatMessage
    {
        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof ChatMessage || $message->getSender()->getId() !== $actorUserId) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Message not found.');
        }

        return $message;
    }
}
