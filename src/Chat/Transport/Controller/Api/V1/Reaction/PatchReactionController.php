<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Reaction;

use App\Chat\Application\Service\ChatAccessResolverService;
use App\Chat\Application\Service\ReactionPayloadService;
use App\Chat\Domain\Enum\ChatReactionType;
use App\Chat\Domain\Repository\Interfaces\ChatMessageReactionRepositoryInterface;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Chat Conversation')]
#[OA\Patch(path: '/v1/chat/private/reactions/{reactionId}', operationId: 'chat_reaction_patch', summary: 'Modifier une réaction', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'reaction', type: 'string', enum: ChatReactionType::VALUES, example: 'love')], example: [
    'reaction' => 'love',
])), tags: ['Chat Message Reaction'], parameters: [new OA\Parameter(name: 'reactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 200, description: 'Réaction modifiée', content: new OA\JsonContent(example: [
    'id' => '8f210e56-6550-4b61-b7f3-8994f5f6dc41',
])), new OA\Response(response: 404, description: 'Réaction introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PatchReactionController
{
    public function __construct(
        private ChatMessageReactionRepositoryInterface $reactionRepository,
        private ChatAccessResolverService $chatAccessResolverService,
        private ReactionPayloadService $reactionPayloadService,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route(path: '/v1/chat/private/reactions/{reactionId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $reactionId, Request $request, User $loggedInUser): JsonResponse
    {
        $reaction = $this->chatAccessResolverService->resolveOwnReaction($reactionId, $loggedInUser);
        $reactionType = $this->reactionPayloadService->extractOptionalReaction($request->toArray());

        if ($reactionType !== null) {
            $reaction->setReaction($reactionType);
            $this->reactionRepository->save($reaction);
            $this->cacheInvalidationService->invalidateConversationCaches($reaction->getMessage()->getConversation()->getChat()->getId(), $loggedInUser->getId());
        }

        return new JsonResponse([
            'id' => $reaction->getId(),
        ]);
    }
}
