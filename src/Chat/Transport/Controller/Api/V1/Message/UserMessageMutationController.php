<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Message\CreateMessageCommand;
use App\Chat\Application\Message\DeleteMessageCommand;
use App\Chat\Application\Message\PatchMessageCommand;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
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
#[OA\Tag(name: 'Chat Message')]
#[OA\Get(
    path: '/v1/chat/private/conversations/{conversationId}',
    operationId: 'chat_message_private_conversation_messages',
    summary: "Lister tous les messages d'une conversation privée",
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste des messages de la conversation'),
        new OA\Response(response: 404, description: 'Conversation introuvable'),
    ]
)]
#[OA\Post(
    path: '/v1/chat/private/conversations/{conversationId}/messages',
    operationId: 'chat_message_create',
    summary: 'Créer un message',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['content'],
            properties: [
                new OA\Property(property: 'content', type: 'string', minLength: 1, example: 'Bonjour, dispo pour un entretien demain ?'),
            ],
            example: [
                'content' => 'Bonjour, dispo pour un entretien demain ?',
            ]
        )
    ),
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    responses: [
        new OA\Response(response: 202, description: 'Commande acceptée'),
        new OA\Response(response: 400, description: 'Payload invalide'),
    ]
)]
#[OA\Patch(
    path: '/v1/chat/private/messages/{messageId}',
    operationId: 'chat_message_patch',
    summary: 'Modifier son message (update)',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', minLength: 1, example: 'Bonjour, finalement mercredi 10h ?'),
                new OA\Property(property: 'read', type: 'boolean', example: true),
            ],
            example: [
                'content' => 'Bonjour, finalement mercredi 10h ?',
                'read' => true,
            ]
        )
    ),
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    responses: [
        new OA\Response(response: 202, description: 'Commande acceptée'),
        new OA\Response(response: 404, description: 'Message introuvable'),
    ]
)]
#[OA\Delete(path: '/v1/chat/private/messages/{messageId}', operationId: 'chat_message_delete', summary: 'Supprimer son message', tags: ['Chat Message'], parameters: [new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 404, description: 'Message introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserMessageMutationController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly MessageServiceInterface $messageService,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_GET])]
    public function list(string $conversationId, User $loggedInUser): JsonResponse
    {
        $conversation = $this->findParticipantConversation($conversationId, $loggedInUser);

        $items = [];
        foreach ($conversation->getMessages()->toArray() as $message) {
            $sender = $message->getSender();
            $senderId = $sender->getId();

            $items[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => [
                    'id' => $senderId,
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'photo' => $sender->getPhoto(),
                    'owner' => $senderId === $loggedInUser->getId(),
                ],
                'attachments' => $message->getAttachments(),
                'read' => $message->isRead(),
                'readAt' => $message->getReadAt()?->format(DATE_ATOM),
                'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'conversationId' => $conversation->getId(),
            'items' => $items,
        ]);
    }

    #[Route(path: '/v1/chat/private/conversations/{conversation}/messages', methods: [Request::METHOD_POST])]
    public function create(Conversation $conversation, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();
        $content = $payload['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "content" is required.');
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new CreateMessageCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            conversationId: $conversation->getId(),
            content: $content,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $messageId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();

        $content = null;
        if (isset($payload['content'])) {
            if (!is_string($payload['content']) || $payload['content'] === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "content" must be a non-empty string when provided.');
            }

            $content = $payload['content'];
        }

        $read = null;
        if (array_key_exists('read', $payload)) {
            if (!is_bool($payload['read'])) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "read" must be a boolean when provided.');
            }

            $read = $payload['read'];
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new PatchMessageCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            messageId: $messageId,
            content: $content,
            read: $read,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $messageId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $messageId, User $loggedInUser): JsonResponse
    {
        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteMessageCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            messageId: $messageId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $messageId,
        ], JsonResponse::HTTP_ACCEPTED);
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
