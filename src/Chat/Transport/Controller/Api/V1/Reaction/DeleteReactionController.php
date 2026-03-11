<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Reaction;

use App\Chat\Application\Service\ChatAccessResolverService;
use App\Chat\Infrastructure\Repository\ChatMessageReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Message Reaction')]
#[OA\Delete(path: '/v1/chat/private/reactions/{reactionId}', operationId: 'chat_reaction_delete', summary: 'Supprimer une réaction', tags: ['Chat Message Reaction'], parameters: [new OA\Parameter(name: 'reactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 204, description: 'Réaction supprimée'), new OA\Response(response: 404, description: 'Réaction introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class DeleteReactionController
{
    public function __construct(
        private readonly ChatMessageReactionRepository $reactionRepository,
        private readonly ChatAccessResolverService $chatAccessResolverService,
        private readonly CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    #[Route(path: '/v1/chat/private/reactions/{reactionId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $reactionId, User $loggedInUser): JsonResponse
    {
        $reaction = $this->chatAccessResolverService->resolveOwnReaction($reactionId, $loggedInUser);
        $chatId = $reaction->getMessage()->getConversation()->getChat()->getId();
        $this->reactionRepository->remove($reaction);
        $this->cacheInvalidationService->invalidateConversationCaches($chatId, $loggedInUser->getId());

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
