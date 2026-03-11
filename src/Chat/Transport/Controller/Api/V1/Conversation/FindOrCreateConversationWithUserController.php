<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Conversation;

use App\Chat\Application\Message\FindOrCreateConversationWithUserCommand;
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
#[OA\Post(path: '/v1/chat/private/conversation/{user}/user', operationId: 'chat_conversation_find_or_create_with_user', summary: 'Trouver ou créer une conversation directe avec un utilisateur', tags: ['Chat Conversation'])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class FindOrCreateConversationWithUserController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly OperationIdGeneratorService $operationIdGeneratorService,
    ) {
    }

    #[Route(path: '/v1/chat/private/conversation/{user}/user', methods: [Request::METHOD_POST])]
    public function __invoke(User $user, User $loggedInUser): JsonResponse
    {
        $operationId = $this->operationIdGeneratorService->generate();
        $this->messageService->sendMessage(new FindOrCreateConversationWithUserCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            targetUserId: $user->getId(),
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'targetUserId' => $user->getId(),
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
