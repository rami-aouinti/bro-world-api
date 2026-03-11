<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Message\MarkConversationMessagesAsReadCommand;
use App\General\Application\Service\OperationIdGeneratorService;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Message')]
#[OA\Post(
    path: '/v1/chat/private/conversations/{conversationId}/messages/read',
    operationId: 'chat_message_mark_conversation_read',
    summary: 'Marquer tous les messages d\'une conversation comme lus',
    tags: ['Chat Message'],
    parameters: [
        new OA\Parameter(name: 'conversationId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000')),
    ],
    responses: [
        new OA\Response(response: 202, description: 'Commande acceptée'),
        new OA\Response(response: 404, description: 'Conversation introuvable'),
    ]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class MarkConversationAsReadController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}/messages/read', methods: [Request::METHOD_POST])]
    public function __invoke(string $conversationId, User $loggedInUser): JsonResponse
    {
        $operationId = $this->operationIdGeneratorService->generate();
        $this->messageService->sendMessage(new MarkConversationMessagesAsReadCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            conversationId: $conversationId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'conversationId' => $conversationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
