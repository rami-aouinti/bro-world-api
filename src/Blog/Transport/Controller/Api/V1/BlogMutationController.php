<?php

declare(strict_types=1);

namespace App\Blog\Transport\Controller\Api\V1;

use App\Blog\Application\Message\CreateBlogCommentCommand;
use App\Blog\Application\Message\CreateBlogPostCommand;
use App\Blog\Application\Message\CreateBlogReactionCommand;
use App\Blog\Application\Message\CreateGeneralBlogCommand;
use App\Blog\Application\Message\DeleteBlogCommentCommand;
use App\Blog\Application\Message\DeleteBlogPostCommand;
use App\Blog\Application\Message\DeleteBlogReactionCommand;
use App\Blog\Application\Message\PatchBlogCommentCommand;
use App\Blog\Application\Message\PatchBlogPostCommand;
use App\Blog\Application\Message\PatchBlogReactionCommand;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Media\Application\Service\MediaUploaderService;
use App\Media\Application\Service\MediaUploadValidationPolicy;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function sprintf;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Blog')]
final readonly class BlogMutationController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private MediaUploaderService $mediaUploaderService,
    ) {
    }

    #[Route('/v1/blogs/general', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/blogs/general', tags: ['Blog'], parameters: [], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(example: [
        'title' => 'General Blog',
    ]))]
    #[OA\Response(response: 202, description: 'General blog creation requested.', content: new OA\JsonContent(example: [
        'status' => 'accepted',
    ]))]
    public function createGeneral(Request $request): JsonResponse
    {
        $user = $request->getUser();
        if (!$user instanceof User || !in_array('ROLE_ROOT', $user->getRoles(), true)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only root can create General blog.');
        }

        $payload = $this->extractPayload($request);
        $this->messageBus->dispatch(new CreateGeneralBlogCommand((string)uniqid('op_', true), $user->getId(), (string)($payload['title'] ?? 'General Blog'), isset($payload['description']) ? (string)$payload['description'] : null));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blogs/{blogId}/posts', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/blogs/{blogId}/posts', tags: ['Blog'], parameters: [new OA\Parameter(name: 'blogId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'blogId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e77')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'content', type: 'string', nullable: true, example: 'Nouveau post produit'), new OA\Property(property: 'filePath', type: 'string', nullable: true, example: 'https://api.example.com/uploads/blog/post.png')], example: [
        'content' => 'Nouveau post produit',
        'filePath' => 'https://api.example.com/uploads/blog/post.png',
    ]))]
    #[OA\Response(response: 202, description: 'Post creation requested.', content: new OA\JsonContent(example: [
        'status' => 'accepted',
    ]))]
    public function createPost(string $blogId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $payload['filePath'] = $this->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $this->messageBus->dispatch(new CreateBlogPostCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $blogId,
            (string)($payload['title'] ?? 'Untitled post'),
            $payload['content'] ?? null,
            $payload['filePath'] ?: null,
            (bool)($payload['isPinned'] ?? false),
        ));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/posts/{postId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'PATCH /v1/blog/posts/{postId}', tags: ['Blog'], parameters: [new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e78')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'content', type: 'string', nullable: true, example: 'Mise a jour du post'), new OA\Property(property: 'filePath', type: 'string', nullable: true, example: 'https://api.example.com/uploads/blog/new-file.png')], example: [
        'content' => 'Mise a jour du post',
        'filePath' => 'https://api.example.com/uploads/blog/new-file.png',
    ]))]
    #[OA\Response(response: 204, description: 'Post updated.')]
    public function patchPost(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $payload['filePath'] = $this->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $this->messageBus->dispatch(new PatchBlogPostCommand((string)uniqid('op_', true), $loggedInUser->getId(), $postId, isset($payload['title']) ? (string)$payload['title'] : null, $payload['content'] ?? null, $payload['filePath'] ?: null, isset($payload['isPinned']) ? (bool)$payload['isPinned'] : null));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/posts/{postId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e78')]
    #[OA\Response(response: 204, description: 'Post deleted.')]
    public function deletePost(string $postId, User $loggedInUser): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBlogPostCommand((string)uniqid('op_', true), $loggedInUser->getId(), $postId));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/posts/{postId}/comments', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/blog/posts/{postId}/comments', tags: ['Blog'], parameters: [new OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'postId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e79')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'content', type: 'string', nullable: true, example: 'Je valide ce point'), new OA\Property(property: 'filePath', type: 'string', nullable: true, example: 'https://api.example.com/uploads/blog/comment.png'), new OA\Property(property: 'parentCommentId', type: 'string', format: 'uuid', nullable: true, example: null)], example: [
        'content' => 'Je valide ce point',
        'parentCommentId' => null,
    ]))]
    #[OA\Response(response: 202, description: 'Comment creation requested.', content: new OA\JsonContent(example: [
        'status' => 'accepted',
    ]))]
    public function createComment(string $postId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $payload['filePath'] = $this->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $this->messageBus->dispatch(new CreateBlogCommentCommand(
            (string)uniqid('op_', true),
            $loggedInUser->getId(),
            $postId,
            $payload['content'] ?? null,
            $payload['filePath'] ?: null,
            $payload['parentCommentId'] ?? null,
        ));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/comments/{commentId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'PATCH /v1/blog/comments/{commentId}', tags: ['Blog'], parameters: [new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e90')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'content', type: 'string', nullable: true, example: 'Commentaire corrige'), new OA\Property(property: 'filePath', type: 'string', nullable: true, example: 'https://api.example.com/uploads/blog/new-file.png')], example: [
        'content' => 'Commentaire corrige',
    ]))]
    #[OA\Response(response: 204, description: 'Comment updated.')]
    public function patchComment(string $commentId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $payload['filePath'] = $this->resolveUploadedFileUrl($request, (string)($payload['filePath'] ?? ''));

        $this->messageBus->dispatch(new PatchBlogCommentCommand((string)uniqid('op_', true), $loggedInUser->getId(), $commentId, $payload['content'] ?? null, $payload['filePath'] ?: null));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/comments/{commentId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e90')]
    #[OA\Response(response: 204, description: 'Comment deleted.')]
    public function deleteComment(string $commentId, User $loggedInUser): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBlogCommentCommand((string)uniqid('op_', true), $loggedInUser->getId(), $commentId));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/comments/{commentId}/reactions', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'POST /v1/blog/comments/{commentId}/reactions', tags: ['Blog'], parameters: [new OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 201, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'commentId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e90')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['type'], properties: [new OA\Property(property: 'type', type: 'string', example: 'heart')], example: [
        'type' => 'heart',
    ]))]
    #[OA\Response(response: 202, description: 'Reaction creation requested.', content: new OA\JsonContent(example: [
        'status' => 'accepted',
    ]))]
    public function createReaction(string $commentId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $this->messageBus->dispatch(new CreateBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $commentId, $this->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse([
            'status' => 'accepted',
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/reactions/{reactionId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'PATCH /v1/blog/reactions/{reactionId}', tags: ['Blog'], parameters: [new OA\Parameter(name: 'reactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))], responses: [new OA\Response(response: 200, description: 'Success.'), new OA\Response(response: 400, description: 'Bad request.'), new OA\Response(response: 401, description: 'Unauthorized.'), new OA\Response(response: 404, description: 'Not found.'), new OA\Response(response: 422, description: 'Validation error.')])]
    #[OA\Parameter(name: 'reactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e91')]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object', required: ['type'], properties: [new OA\Property(property: 'type', type: 'string', example: 'laugh')], example: [
        'type' => 'laugh',
    ]))]
    #[OA\Response(response: 204, description: 'Reaction updated.')]
    public function patchReaction(string $reactionId, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $this->messageBus->dispatch(new PatchBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $reactionId, $this->parseReactionType((string)($payload['type'] ?? 'like'))));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/reactions/{reactionId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'reactionId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '0195f4b9-4f2b-7c9a-8e6d-6f9b7d4a6e91')]
    #[OA\Response(response: 204, description: 'Reaction deleted.')]
    public function deleteReaction(string $reactionId, User $loggedInUser): JsonResponse
    {
        $this->messageBus->dispatch(new DeleteBlogReactionCommand((string)uniqid('op_', true), $loggedInUser->getId(), $reactionId));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    private function extractPayload(Request $request): array
    {
        $payload = (array)json_decode((string)$request->getContent(), true);

        if ($payload === []) {
            $payload = $request->request->all();
        }

        return $payload;
    }

    private function parseReactionType(string $reactionType): BlogReactionType
    {
        $parsed = BlogReactionType::tryFrom($reactionType);

        if ($parsed instanceof BlogReactionType) {
            return $parsed;
        }

        throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, sprintf('Unsupported reaction type "%s".', $reactionType));
    }

    private function resolveUploadedFileUrl(Request $request, string $fallbackUrl): string
    {
        $file = $request->files->get('file');

        if (!$file instanceof UploadedFile) {
            return $fallbackUrl;
        }

        $uploaded = $this->mediaUploaderService->upload(
            $request,
            [$file],
            '/uploads/blog',
            new MediaUploadValidationPolicy(
                maxSizeInBytes: 10 * 1024 * 1024,
                allowedMimeTypes: [
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                    'application/pdf',
                ],
                allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
            ),
        );

        return (string)($uploaded[0]['url'] ?? $fallbackUrl);
    }
}
