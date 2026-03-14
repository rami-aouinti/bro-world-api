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
use App\General\Application\Service\MercurePublisher;
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
        private MercurePublisher $mercurePublisher,
    ) {
    }

    public function __invoke(CreateMessageCommand $command): string
    {
        /** @var array{chatId: string, message: ChatMessage} $result */
        $result = $this->messageRepository->getEntityManager()->getConnection()->transactional(function () use ($command): array {
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
                ->setMetadata([]);

            $conversation->setLastMessageAt(new \DateTimeImmutable());
            $this->conversationRepository->save($conversation, false);

            $this->messageRepository->save($message);

            return [
                'chatId' => $conversation->getChat()->getId(),
                'message' => $message,
            ];
        });

        $this->cacheInvalidationService->invalidateConversationCaches($result['chatId'], $command->actorUserId);

        $this->mercurePublisher->publish('/conversations/' . $command->conversationId . '/messages', [
            'id' => $result['message']->getId(),
            'conversationId' => $command->conversationId,
            'senderId' => $result['message']->getSender()->getId(),
            'content' => $result['message']->getContent(),
            'attachments' => $result['message']->getAttachments(),
            'createdAt' => $result['message']->getCreatedAt()?->format(DATE_ATOM),
        ]);

        return $result['message']->getId();
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
