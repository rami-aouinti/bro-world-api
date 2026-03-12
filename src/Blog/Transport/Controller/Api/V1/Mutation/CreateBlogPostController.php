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

use function preg_replace;
use function strtolower;
use function trim;

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
        $mediaUrls = $this->requestService->resolveUploadedFileUrls($request);
        $contentData = $this->requestService->normalizePostContent($payload);
        $slug = $this->slugify((string)($payload['slug'] ?? $payload['title'] ?? 'post'));

        $entityId = $this->handler->__invoke(new CreateBlogPostCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $blogId,
            (string)($payload['title'] ?? 'Untitled post'),
            $slug,
            $contentData['content'],
            $payload['filePath'] ?: null,
            $mediaUrls,
            $contentData['sharedUrl'],
            isset($payload['parentPostId']) ? (string)$payload['parentPostId'] : null,
            (bool)($payload['isPinned'] ?? false)
        ));

        return new JsonResponse([
            'status' => 'accepted',
            'id' => is_string($entityId) ? $entityId : null,
            'slug' => $slug,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    private function slugify(string $value): string
    {
        $slug = trim(strtolower((string)preg_replace('/[^a-zA-Z0-9]+/', '-', $value)), '-');

        return $slug !== '' ? $slug . '-' . substr((string)uniqid('', true), -6) : 'post-' . substr((string)uniqid('', true), -6);
    }
}
