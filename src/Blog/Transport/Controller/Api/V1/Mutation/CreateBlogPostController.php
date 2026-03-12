<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\CreateBlogPostCommand;
use App\Blog\Application\MessageHandler\CreateBlogPostCommandHandler;
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
final readonly class CreateBlogPostController
{
    public function __construct(
        private CreateBlogPostCommandHandler $handler,
        private BlogMutationRequestService $requestService,
    ) {
    }

    #[Route('/v1/private/blogs/{blogId}/posts', methods: [Request::METHOD_POST])]
    public function __invoke(string $blogId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $payload['filePath'] = $this->requestService->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $entityId = $this->handler->__invoke(new CreateBlogPostCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $blogId, (string)($payload['title'] ?? 'Untitled post'),
                $payload['content'] ?? null,
            $payload['filePath'] ?: null,
            (bool)($payload['isPinned'] ?? false)
        ));

        /** @var HandledStamp|null $handled */
        $handled = $envelope->last(HandledStamp::class);
        $entityId = $handled?->getResult();

        return new JsonResponse([
            'status' => 'accepted',
            'id' => is_string($entityId) ? $entityId : null,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
