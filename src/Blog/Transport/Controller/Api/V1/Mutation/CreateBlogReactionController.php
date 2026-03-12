<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateBlogReactionCommand;
use App\Blog\Application\MessageHandler\CreateBlogReactionCommandHandler;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class CreateBlogReactionController
{
    public function __construct(
        private CreateBlogReactionCommandHandler $handler,
        private BlogMutationRequestService $requestService,
    ) {
    }

    #[Route('/v1/private/blog/comments/{commentId}/reactions', methods: [Request::METHOD_POST])]
    public function __invoke(string $commentId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $entityId = $this->handler->__invoke(new CreateBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $commentId, $this->requestService->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse([
            'status' => 'accepted',
            'id' => is_string($entityId) ? $entityId : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
