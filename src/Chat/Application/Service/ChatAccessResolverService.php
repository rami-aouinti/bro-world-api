<?php

declare(strict_types=1);

namespace App\Chat\Application\Service;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\ChatMessageReaction;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatMessageReactionRepository;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ChatAccessResolverService
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private ChatMessageRepository $messageRepository,
        private ChatMessageReactionRepository $reactionRepository,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function resolveParticipantConversation(string $conversationId, User $loggedInUser): Conversation
    {
        $conversation = $this->conversationRepository->find($conversationId);
        if (!$conversation instanceof Conversation) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        $participant = $this->participantRepository->findOneByConversationAndUser($conversation, $loggedInUser);
        if (!$participant instanceof ConversationParticipant) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Conversation not found.');
        }

        return $conversation;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function resolveAccessibleMessage(string $messageId, User $loggedInUser): ChatMessage
    {
        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof ChatMessage) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Message not found.');
        }

        $participant = $this->participantRepository->findOneByConversationAndUser($message->getConversation(), $loggedInUser);
        if (!$participant instanceof ConversationParticipant) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Message not found.');
        }

        return $message;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function resolveOwnReaction(string $reactionId, User $loggedInUser): ChatMessageReaction
    {
        $reaction = $this->reactionRepository->find($reactionId);
        if (!$reaction instanceof ChatMessageReaction || $reaction->getUser()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.');
        }

        return $reaction;
    }
}
