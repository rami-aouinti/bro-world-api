<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Service\ChatAccessResolverService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ListConversationMessagesController
{
    public function __construct(
        private ChatAccessResolverService $chatAccessResolverService,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_GET])]
    public function __invoke(string $conversationId, User $loggedInUser): JsonResponse
    {
        $conversation = $this->chatAccessResolverService->resolveParticipantConversation($conversationId, $loggedInUser);
        $lastReadMessageAt = null;
        foreach ($conversation->getParticipants() as $participant) {
            if ($participant->getUser()->getId() === $loggedInUser->getId()) {
                $lastReadMessageAt = $participant->getLastReadMessageAt();
                break;
            }
        }

        $items = [];
        foreach ($conversation->getMessages()->toArray() as $message) {
            $sender = $message->getSender();
            $senderId = $sender->getId();

            $isOwner = $senderId === $loggedInUser->getId();
            $isRead = $isOwner || ($lastReadMessageAt !== null && $message->getCreatedAt() !== null && $message->getCreatedAt() <= $lastReadMessageAt);

            $items[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => [
                    'id' => $senderId,
                    'firstName' => $sender->getFirstName(),
                    'lastName' => $sender->getLastName(),
                    'photo' => $sender->getPhoto(),
                    'owner' => $isOwner,
                ],
                'attachments' => $message->getAttachments(),
                'read' => $isRead,
                'readAt' => $isRead && !$isOwner ? $lastReadMessageAt?->format(DATE_ATOM) : null,
                'createdAt' => $message->getCreatedAt()?->format(DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'conversationId' => $conversation->getId(),
            'items' => $items,
        ]);
    }
}
