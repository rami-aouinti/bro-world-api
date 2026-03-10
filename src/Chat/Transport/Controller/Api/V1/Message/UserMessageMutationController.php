<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Domain\Entity\ChatMessage;
use App\Chat\Domain\Entity\Conversation;
use App\Chat\Domain\Entity\ConversationParticipant;
use App\Chat\Infrastructure\Repository\ChatMessageRepository;
use App\Chat\Infrastructure\Repository\ConversationParticipantRepository;
use App\Chat\Infrastructure\Repository\ConversationRepository;
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
#[OA\Post(
    path: '/v1/chat/private/conversations/{conversationId}/messages',
    operationId: 'chat_message_create',
    summary: 'Créer un message',
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['content'],
            properties: [
                new OA\Property(property: 'content', type: 'string', minLength: 1, example: 'Bonjour, dispo pour un entretien demain ?'),
            ],
            example: ['content' => 'Bonjour, dispo pour un entretien demain ?']
        )
    ),
    responses: [
        new OA\Response(
            response: 201,
            description: 'Message créé',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')], example: ['id' => '8f210e56-6550-4b61-b7f3-8994f5f6dc41'])
        ),
        new OA\Response(
            response: 400,
            description: 'Payload invalide',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Field "content" is required.'])
        ),
        new OA\Response(
            response: 404,
            description: 'Conversation introuvable',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Conversation not found.'])
        ),
    ]
)]
#[OA\Patch(
    path: '/v1/chat/private/messages/{messageId}',
    operationId: 'chat_message_patch',
    summary: 'Modifier son message (update)',
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'content', type: 'string', minLength: 1, example: 'Bonjour, finalement mercredi 10h ?'),
                new OA\Property(property: 'read', type: 'boolean', example: true),
            ],
            example: ['content' => 'Bonjour, finalement mercredi 10h ?', 'read' => true]
        )
    ),
    responses: [
        new OA\Response(
            response: 200,
            description: 'Message mis à jour',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'id', type: 'string', format: 'uuid')], example: ['id' => '8f210e56-6550-4b61-b7f3-8994f5f6dc41'])
        ),
        new OA\Response(
            response: 404,
            description: 'Message introuvable',
            content: new OA\JsonContent(properties: [new OA\Property(property: 'message', type: 'string')], example: ['message' => 'Message not found.'])
        ),
    ]
)]
#[OA\Delete(path: '/v1/chat/private/messages/{messageId}', operationId: 'chat_message_delete', summary: 'Supprimer son message', tags: ['Chat Message'], parameters: [new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 204, description: 'Supprimé'), new OA\Response(response: 404, description: 'Message introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserMessageMutationController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationParticipantRepository $participantRepository,
        private readonly ChatMessageRepository $messageRepository,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}/messages', methods: [Request::METHOD_POST])]
    public function create(string $conversationId, Request $request, User $loggedInUser): JsonResponse
    {
        $conversation = $this->findParticipantConversation($conversationId, $loggedInUser);
        $payload = $request->toArray();

        $content = $payload['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "content" is required.');
        }

        $message = (new ChatMessage())
            ->setConversation($conversation)
            ->setSender($loggedInUser)
            ->setContent($content)
            ->setAttachments([])
            ->setRead(false);

        $this->messageRepository->save($message);

        return new JsonResponse(['id' => $message->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $messageId, Request $request, User $loggedInUser): JsonResponse
    {
        $message = $this->findOwnMessage($messageId, $loggedInUser);
        $payload = $request->toArray();

        $updated = false;

        if (isset($payload['content']) && is_string($payload['content']) && $payload['content'] !== '') {
            $message->setContent($payload['content']);
            $updated = true;
        }

        if (array_key_exists('read', $payload) && is_bool($payload['read'])) {
            $message->setRead((bool) $payload['read']);
            $updated = true;
        }

        if ($updated) {
            $this->messageRepository->save($message);
        }

        return new JsonResponse(['id' => $message->getId()]);
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $messageId, User $loggedInUser): JsonResponse
    {
        $message = $this->findOwnMessage($messageId, $loggedInUser);
        $this->messageRepository->remove($message);

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

    private function findOwnMessage(string $messageId, User $loggedInUser): ChatMessage
    {
        $message = $this->messageRepository->find($messageId);
        if (!$message instanceof ChatMessage || $message->getSender()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Message not found.');
        }

        return $message;
    }
}
