<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1\Mutation;

use App\Blog\Application\Message\PatchBlogPostCommand;
use App\Blog\Application\Service\BlogMutationRequestService;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class PatchBlogPostController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private BlogMutationRequestService $requestService,
    ) {
    }

    /**
     * @throws JsonException
     * @throws ExceptionInterface
     */
    #[Route('/v1/private/blog/posts/{postId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->requestService->extractPayload($request);
        $payload['filePath'] = $this->requestService->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));
        $contentData = $this->requestService->normalizePostContent($payload);
        $mediaUrls = $this->requestService->resolveUploadedFileUrls($request);

        $this->messageBus->dispatch(new PatchBlogPostCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $postId,
            isset($payload['title']) ? (string)$payload['title'] : null,
            $contentData['content'],
            $payload['filePath'] ?: null,
            $mediaUrls !== [] ? $mediaUrls : null,
            $contentData['sharedUrl'],
            isset($payload['isPinned']) ? (bool)$payload['isPinned'] : null
        ));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
