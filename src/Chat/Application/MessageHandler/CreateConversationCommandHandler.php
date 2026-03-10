<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\CreateConversationCommand;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateConversationCommandHandler
{
    public function __construct(
        private ChatRepository $chatRepository,
        private UserRepository $userRepository,
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    public function __invoke(CreateConversationCommand $command): void
    {
        $entityManager = $this->conversationRepository->getEntityManager();

        $result = $entityManager->getConnection()->transactional(function () use ($command): array {
            $chat = $this->chatRepository->find($command->chatId);
            if (!$chat instanceof Chat) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Chat not found.');
            }

            $actor = $this->userRepository->find($command->actorUserId);
            if (!$actor instanceof User) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
            }

            $targetUser = $this->userRepository->find($command->targetUserId);
            if (!$targetUser instanceof User) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown userId.');
            }

            $conversation = (new Conversation())->setChat($chat);
            $this->conversationRepository->save($conversation, false);

            $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($actor), false);
            if ($targetUser->getId() !== $actor->getId()) {
                $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($targetUser), false);
            }

            $entityManager->flush();

            return ['chatId' => $chat->getId(), 'actorId' => $actor->getId(), 'targetId' => $targetUser->getId()];
        });

        $this->cacheInvalidationService->invalidateConversationCaches($result['chatId'], $result['actorId']);
        $this->cacheInvalidationService->invalidateConversationCaches($result['chatId'], $result['targetId']);
    }
}
