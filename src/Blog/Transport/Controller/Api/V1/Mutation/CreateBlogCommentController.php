<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateBlogCommentCommand;
use App\Blog\Application\MessageHandler\CreateBlogCommentCommandHandler;
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
final readonly class CreateBlogCommentController
{
    public function __construct(
        private CreateBlogCommentCommandHandler $handler,
        private BlogMutationRequestService $requestService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ORMException
     */
    #[Route('/v1/private/blog/posts/{postId}/comments', methods: [Request::METHOD_POST])]
    public function __invoke(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $payload['filePath'] = $this->requestService->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $entityId = $this->handler->__invoke(new CreateBlogCommentCommand((string)uniqid('op_', true), $loggedInUser->getId(), $postId, $payload['content'] ?? null, $payload['filePath'] ?: null, $payload['parentCommentId'] ?? null));

        return new JsonResponse([
            'status' => 'accepted',
            'id' => is_string($entityId) ? $entityId : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
