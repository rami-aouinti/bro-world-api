<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateBlogPostReactionCommand;
use App\Blog\Application\MessageHandler\CreateBlogPostReactionCommandHandler;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
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
final readonly class CreateBlogPostReactionController
{
    public function __construct(
        private CreateBlogPostReactionCommandHandler $handler,
        private BlogMutationRequestService $requestService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ORMException
     */
    #[Route('/v1/private/blog/posts/{postId}/reactions', methods: [Request::METHOD_POST])]
    public function __invoke(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $entityId = $this->handler->__invoke(new CreateBlogPostReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $postId, $this->requestService->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse([
            'status' => 'accepted',
            'id' => is_string($entityId) ? $entityId : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
