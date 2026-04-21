<?php

declare(strict_types=1);

namespace App\Chat\Application\MessageHandler;

use App\Chat\Application\Message\FindOrCreateConversationWithUserCommand;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Domain\Enum\ConversationParticipantRole;
use App\Chat\Domain\Enum\ConversationType;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\MercurePublisher;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
final readonly class FindOrCreateConversationWithUserCommandHandler
{
    public function __construct(
        private UserRepository $userRepository,
        private ConversationRepository $conversationRepository,
        private ConversationParticipantRepository $participantRepository,
        private ChatRepository $chatRepository,
        private CacheInvalidationService $cacheInvalidationService,
        private MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * @throws Throwable
     * @throws JsonException
     */
    public function __invoke(FindOrCreateConversationWithUserCommand $command): void
    {
        $result = $this->conversationRepository->getEntityManager()->getConnection()->transactional(function () use ($command): array {
            $actor = $this->userRepository->find($command->actorUserId);
            if (!$actor instanceof User) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
            }

            $targetUser = $this->userRepository->find($command->targetUserId);
            if (!$targetUser instanceof User) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown userId.');
            }

            $existingConversation = $this->conversationRepository->findDirectConversationBetweenUsers($actor, $targetUser);
            if ($existingConversation instanceof Conversation) {
                return [
                    'chatId' => $existingConversation->getChat()->getId(),
                    'conversationId' => $existingConversation->getId(),
                    'actorUserId' => $actor->getId(),
                    'targetUserId' => $targetUser->getId(),
                    'created' => false,
                ];
            }

            $chat = $this->chatRepository->findChatForDirectConversation($actor, $targetUser);
            if (!$chat instanceof Chat) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'No chat available for these users in a shared application scope.');
            }

            $conversation = (new Conversation())
                ->setChat($chat)
                ->setType(ConversationType::DIRECT);
            $this->conversationRepository->save($conversation, false);
            $this->participantRepository->save((new ConversationParticipant())
                ->setConversation($conversation)
                ->setUser($actor)
                ->setRole(ConversationParticipantRole::OWNER), false);
            if ($targetUser->getId() !== $actor->getId()) {
                $this->participantRepository->save((new ConversationParticipant())
                    ->setConversation($conversation)
                    ->setUser($targetUser)
                    ->setRole(ConversationParticipantRole::MEMBER), false);
            }

            $this->conversationRepository->getEntityManager()->flush();

            return [
                'chatId' => $chat->getId(),
                'conversationId' => $conversation->getId(),
                'actorUserId' => $actor->getId(),
                'targetUserId' => $targetUser->getId(),
                'created' => true,
            ];
        });

        $this->cacheInvalidationService->invalidateConversationCaches($result['chatId'], $result['actorUserId']);
        $this->cacheInvalidationService->invalidateConversationCaches($result['chatId'], $result['targetUserId']);

        $payload = [
            'operationId' => $command->operationId,
            'conversationId' => $result['conversationId'],
            'chatId' => $result['chatId'],
            'actorUserId' => $result['actorUserId'],
            'targetUserId' => $result['targetUserId'],
            'created' => $result['created'],
        ];

        $this->mercurePublisher->publish('/users/' . $result['actorUserId'] . '/conversations', $payload, false);
        if ($result['targetUserId'] !== $result['actorUserId']) {
            $this->mercurePublisher->publish('/users/' . $result['targetUserId'] . '/conversations', $payload, false);
        }
    }
}
