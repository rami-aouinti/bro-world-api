<?php

declare(strict_types=1);

namespace App\Chat\Transport\Controller\Api\V1\Reaction;

use App\Chat\Application\Service\ChatAccessResolverService;
use App\Chat\Application\Service\ReactionPayloadService;
use App\Chat\Domain\Entity\ChatMessageReaction;
use App\Chat\Domain\Enum\ChatReactionType;
use App\Chat\Domain\Repository\Interfaces\ChatMessageReactionRepositoryInterface;
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
#[OA\Post(path: '/v1/chat/private/messages/{messageId}/reactions', operationId: 'chat_reaction_create', summary: 'Créer une réaction', tags: ['Chat Message Reaction'], parameters: [new OA\Parameter(name: 'messageId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['reaction'], properties: [new OA\Property(property: 'reaction', type: 'string', enum: ChatReactionType::VALUES, example: 'like')], example: [
    'reaction' => 'like',
])), responses: [new OA\Response(response: 201, description: 'Réaction créée', content: new OA\JsonContent(example: [
    'id' => '8f210e56-6550-4b61-b7f3-8994f5f6dc41',
])), new OA\Response(response: 400, description: 'Payload invalide'), new OA\Response(response: 404, description: 'Message introuvable')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class CreateReactionController
{
    public function __construct(
        private readonly ChatMessageReactionRepositoryInterface $reactionRepository,
        private readonly ChatAccessResolverService $chatAccessResolverService,
        private readonly ReactionPayloadService $reactionPayloadService,
        private readonly CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    #[Route(path: '/v1/chat/private/messages/{messageId}/reactions', methods: [Request::METHOD_POST])]
    public function __invoke(string $messageId, Request $request, User $loggedInUser): JsonResponse
    {
        $message = $this->chatAccessResolverService->resolveAccessibleMessage($messageId, $loggedInUser);
        $reactionType = $this->reactionPayloadService->extractRequiredReaction($request->toArray());

        $reaction = (new ChatMessageReaction())
            ->setMessage($message)
            ->setUser($loggedInUser)
            ->setReaction($reactionType);

        $this->reactionRepository->save($reaction);
        $this->cacheInvalidationService->invalidateConversationCaches($message->getConversation()->getChat()->getId(), $loggedInUser->getId());

        return new JsonResponse([
            'id' => $reaction->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
