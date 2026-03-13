<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Message\PatchMessageCommand;
use App\Chat\Application\Service\MessagePayloadService;
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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PatchMessageController
{
    public function __construct(
        private MessageServiceInterface $messageService,
        private MessagePayloadService $messagePayloadService,
        private OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $messageId, Request $request, User $loggedInUser): JsonResponse
    {
        $fields = $this->messagePayloadService->extractPatchFields($request->toArray());
        $operationId = $this->operationIdGeneratorService->generate();

        $this->messageService->sendMessage(new PatchMessageCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            messageId: $messageId,
            content: $fields['content'],
            read: $fields['read'],
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $messageId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
