<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Message;

use App\Chat\Application\Message\CreateMessageCommand;
use App\Chat\Application\Service\MessagePayloadService;
use App\General\Application\Service\OperationIdGeneratorService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
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
        new OA\Response(response: 202, description: 'Commande acceptée', content: new OA\JsonContent(example: [
            'operationId' => 'op_123',
            'id' => '550e8400-e29b-41d4-a716-446655440000',
        ])),
        new OA\Response(response: 400, description: 'Payload invalide'),
    ]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class CreateMessageController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly MessagePayloadService $messagePayloadService,
        private readonly OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversations/{conversationId}/messages', methods: [Request::METHOD_POST])]
    public function __invoke(string $conversationId, Request $request, User $loggedInUser): JsonResponse
    {
        $content = $this->messagePayloadService->extractRequiredContent($request->toArray());
        $operationId = $this->operationIdGeneratorService->generate();

        $envelope = $this->messageBus->dispatch(new CreateMessageCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            conversationId: $conversationId,
            content: $content,
        ));

        /** @var HandledStamp|null $handled */
        $handled = $envelope->last(HandledStamp::class);
        $entityId = $handled?->getResult();

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => is_string($entityId) ? $entityId : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
