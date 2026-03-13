<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\PatchConversationCommand;
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
use Throwable;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Patch(path: '/v1/chat/private/conversations/{conversationId}', operationId: 'chat_conversation_patch', summary: 'Ajouter un participant (update)', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['userId'], properties: [new OA\Property(property: 'userId', type: 'string', format: 'uuid')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PatchConversationController
{
    public function __construct(
        private MessageServiceInterface     $messageService,
        private ConversationPayloadService  $conversationPayloadService,
        private OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $conversationId, Request $request, User $loggedInUser): JsonResponse
    {
        $targetUserId = $this->conversationPayloadService->extractRequiredUserId($request->toArray());
        $operationId = $this->operationIdGeneratorService->generate();

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
}
