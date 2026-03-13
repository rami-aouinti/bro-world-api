<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\DeleteConversationCommand;
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
#[OA\Delete(path: '/v1/chat/private/conversations/{conversationId}', operationId: 'chat_conversation_delete', summary: 'Supprimer une conversation', tags: ['Chat Conversation'], responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class DeleteConversationController
{
    public function __construct(
        private MessageServiceInterface     $messageService,
        private OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(path: '/v1/chat/private/conversations/{conversationId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $conversationId, User $loggedInUser): JsonResponse
    {
        $operationId = $this->operationIdGeneratorService->generate();
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
}
