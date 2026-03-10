<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
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
#[OA\Post(
    path: '/v1/chat/private/chats/{chatId}/conversations',
    operationId: 'chat_conversation_create',
    summary: 'Créer une conversation',
    tags: ['Chat Conversation'],
    parameters: [
        new OA\Parameter(name: 'chatId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', example: '7c9e6679-7425-40de-944b-e07fc1f90ae7'),
            ],
            example: ['userId' => '7c9e6679-7425-40de-944b-e07fc1f90ae7']
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Conversation créée',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')], example: ['id' => '2a4d0a6c-9465-4d36-8f08-b6302ea62b44'])
        ),
        new OA\Response(
            response: 400,
            description: 'Payload invalide',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Field "userId" is required.'])
        ),
        new OA\Response(
            response: 404,
            description: 'Chat introuvable',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Chat not found.'])
        ),
    ]
)]
#[OA\Patch(
    path: '/v1/chat/private/conversations/{conversationId}',
    operationId: 'chat_conversation_patch',
    summary: 'Ajouter un participant (update)',
    tags: ['Chat Conversation'],
    parameters: [
        new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['userId'],
            properties: [
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', example: '7c9e6679-7425-40de-944b-e07fc1f90ae7'),
            ],
            example: ['userId' => '7c9e6679-7425-40de-944b-e07fc1f90ae7']
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Participant ajouté',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')], example: ['id' => '2a4d0a6c-9465-4d36-8f08-b6302ea62b44'])
        ),
        new OA\Response(
            response: 400,
            description: 'Payload invalide',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Unknown userId.'])
        ),
        new OA\Response(
            response: 404,
            description: 'Conversation introuvable',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Conversation not found.'])
        ),
    ]
)]
#[OA\Delete(path: '/v1/chat/private/conversations/{conversationId}', operationId: 'chat_conversation_delete', summary: 'Supprimer une conversation', tags: ['Chat Conversation'], parameters: [new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 204, description: 'Supprimée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserConversationMutationController
{
    public function __construct(
        private readonly ChatRepository $chatRepository,
        private readonly UserRepository $userRepository,
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
    ) {
    }

    #[Route(path: '/v1/chat/private/chats/{chatId}/conversations', methods: [Request::METHOD_POST])]
    public function create(string $chatId, Request $request, User $loggedInUser): JsonResponse
    {
        $chat = $this->chatRepository->find($chatId);
        if ($chat === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Chat not found.');
        }

        $payload = $request->toArray();
        $targetUserId = $payload['userId'] ?? null;
        if (!is_string($targetUserId) || $targetUserId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "userId" is required.');
        }

        $targetUser = $this->userRepository->find($targetUserId);
        if (!$targetUser instanceof User) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown userId.');
        }

        $conversation = (new Conversation())->setChat($chat);
        $this->conversationRepository->save($conversation, false);

        $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($loggedInUser), false);
        if ($targetUser->getId() !== $loggedInUser->getId()) {
            $this->participantRepository->save((new ConversationParticipant())->setConversation($conversation)->setUser($targetUser), false);
        }
        $this->conversationRepository->getEntityManager()->flush();

        return new JsonResponse(['id' => $conversation->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $conversationId, Request $request, User $loggedInUser): JsonResponse
    {
        $conversation = $this->findParticipantConversation($conversationId, $loggedInUser);
        $payload = $request->toArray();

        $targetUserId = $payload['userId'] ?? null;
        if (!is_string($targetUserId) || $targetUserId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "userId" is required.');
        }

        $targetUser = $this->userRepository->find($targetUserId);
        if (!$targetUser instanceof User) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown userId.');
        }

        $alreadyParticipant = $this->participantRepository->findOneByConversationAndUser($conversation, $targetUser);
        if (!$alreadyParticipant instanceof ConversationParticipant) {
            $this->participantRepository->save(
                (new ConversationParticipant())->setConversation($conversation)->setUser($targetUser)
            );
        }

        return new JsonResponse(['id' => $conversation->getId()]);
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $conversationId, User $loggedInUser): JsonResponse
    {
        $conversation = $this->findParticipantConversation($conversationId, $loggedInUser);
        $this->conversationRepository->remove($conversation);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    private function findParticipantConversation(string $conversationId, User $loggedInUser): Conversation
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
}
