<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\CreateConversationCommand;
use App\Chat\Application\Message\DeleteConversationCommand;
use App\Chat\Application\Message\PatchConversationCommand;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Post(path: '/v1/chat/private/chats/{chatId}/conversations', operationId: 'chat_conversation_create', summary: 'Créer une conversation', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['userId'], properties: [new OA\Property(property: 'userId', type: 'string', format: 'uuid')])))]
#[OA\Patch(path: '/v1/chat/private/conversations/{conversationId}', operationId: 'chat_conversation_patch', summary: 'Ajouter un participant (update)', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['userId'], properties: [new OA\Property(property: 'userId', type: 'string', format: 'uuid')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
#[OA\Delete(path: '/v1/chat/private/conversations/{conversationId}', operationId: 'chat_conversation_delete', summary: 'Supprimer une conversation', tags: ['Chat Conversation'], responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
#[OA\Post(path: '/v1/chat/private/conversation/{userId}/user', operationId: 'chat_conversation_find_or_create_with_user', summary: 'Trouver ou créer une conversation directe avec un utilisateur', tags: ['Chat Conversation'])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserConversationMutationController
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly UserRepository $userRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly MessageServiceInterface $messageService,
    ) {
    }

    #[Route(path: '/v1/chat/private/chats/{chatId}/conversations', methods: [Request::METHOD_POST])]
    public function create(string $chatId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();
        $targetUserId = $payload['userId'] ?? null;
        if (!is_string($targetUserId) || $targetUserId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "userId" is required.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new CreateConversationCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            chatId: $chatId,
            targetUserId: $targetUserId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $conversationId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();
        $targetUserId = $payload['userId'] ?? null;
        if (!is_string($targetUserId) || $targetUserId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "userId" is required.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new PatchConversationCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            conversationId: $conversationId,
            targetUserId: $targetUserId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $conversationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $conversationId, User $loggedInUser): JsonResponse
    {
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteConversationCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            conversationId: $conversationId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $conversationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/chat/private/conversation/{userId}/user', methods: [Request::METHOD_POST])]
    public function findOrCreateWithUser(string $userId, User $loggedInUser): JsonResponse
    {
        if ($userId === '' || $userId === $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid target userId.');
        }

        $targetUser = $this->userRepository->find($userId);
        if (!$targetUser instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
        }

        $conversation = $this->conversationRepository->findDirectConversationBetweenUsers($loggedInUser, $targetUser);
        if ($conversation instanceof Conversation) {
            return new JsonResponse($this->normalizeConversation($conversation, $loggedInUser), JsonResponse::HTTP_OK);
        }

        $chat = $this->chatRepository->findBy([], [
            'createdAt' => 'ASC',
        ], 1)[0] ?? null;
        if (!$chat instanceof Chat) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'No chat available to create a conversation.');
        }

        $conversation = (new Conversation())->setChat($chat);
        $this->conversationRepository->save($conversation, false);
        $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($loggedInUser), false);
        $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($targetUser), false);
        $this->conversationRepository->getEntityManager()->flush();

        return new JsonResponse($this->normalizeConversation($conversation, $loggedInUser), JsonResponse::HTTP_CREATED);
    }

    private function normalizeConversation(Conversation $conversation, User $loggedInUser): array
    {
        $loggedInUserId = $loggedInUser->getId();

        $messages = array_map(static function (\App\Chat\Domain\Entity\ChatMessage $message) use ($loggedInUserId): array {
            $sender = $message->getSender();
            $senderId = $sender->getId();

            return [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'read' => $message->isRead(),
                'readAt' => $message->getReadAt()?->format(DATE_ATOM),
                'attachments' => $message->getAttachments(),
                'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
                'sender' => [
                    'id' => $senderId,
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'photo' => $sender->getPhoto(),
                    'owner' => $senderId === $loggedInUserId,
                ],
            ];
        }, $conversation->getMessages()->toArray());

        return [
            'id' => $conversation->getId(),
            'chatId' => $conversation->getChat()->getId(),
            'participants' => array_map(static function (ConversationParticipant $participant) use ($loggedInUserId): array {
                $participantUser = $participant->getUser();

                return [
                    'id' => $participant->getId(),
                    'user' => [
                        'id' => $participantUser->getId(),
                        'firstName' => $participantUser->getFirstName(),
                        'lastName' => $participantUser->getLastName(),
                        'photo' => $participantUser->getPhoto(),
                        'owner' => $participantUser->getId() === $loggedInUserId,
                    ],
                ];
            }, $conversation->getParticipants()->toArray()),
            'messages' => $messages,
            'createdAt' => $conversation->getCreatedAt()?->format(DATE_ATOM),
        ];
    }
}
