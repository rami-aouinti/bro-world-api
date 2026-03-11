<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\CreateConversationCommand;
use App\Chat\Application\Message\DeleteConversationCommand;
use App\Chat\Application\Message\FindOrCreateConversationWithUserCommand;
use App\Chat\Application\Message\PatchConversationCommand;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\User\Domain\Entity\User;
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

    #[Route(path: '/v1/chat/private/conversation/{user}/user', methods: [Request::METHOD_POST])]
    public function findOrCreateWithUser(User $user, User $loggedInUser): JsonResponse
    {
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new FindOrCreateConversationWithUserCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            targetUserId: $user->getId(),
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'targetUserId' => $user->getId(),
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
