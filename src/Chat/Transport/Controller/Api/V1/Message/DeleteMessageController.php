<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Message\DeleteMessageCommand;
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
#[OA\Delete(path: '/v1/chat/private/messages/{messageId}', operationId: 'chat_message_delete', summary: 'Supprimer son message', tags: ['Chat Message'], parameters: [new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 404, description: 'Message introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class DeleteMessageController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $messageId, User $loggedInUser): JsonResponse
    {
        $operationId = $this->operationIdGeneratorService->generate();
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
}
