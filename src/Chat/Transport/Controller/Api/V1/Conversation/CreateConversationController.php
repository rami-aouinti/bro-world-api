<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\CreateConversationCommand;
use App\Chat\Application\Service\ConversationPayloadService;
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
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Post(path: '/v1/chat/private/chats/{chatId}/conversations', operationId: 'chat_conversation_create', summary: 'Créer une conversation', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['userId'], properties: [new OA\Property(property: 'userId', type: 'string', format: 'uuid')])))]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class CreateConversationController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly ConversationPayloadService $conversationPayloadService,
        private readonly OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/chats/{chatId}/conversations', methods: [Request::METHOD_POST])]
    public function __invoke(string $chatId, Request $request, User $loggedInUser): JsonResponse
    {
        $targetUserId = $this->conversationPayloadService->extractRequiredUserId($request->toArray());
        $operationId = $this->operationIdGeneratorService->generate();

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
}
