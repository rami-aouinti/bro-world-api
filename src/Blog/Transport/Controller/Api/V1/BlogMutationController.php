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
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class BlogMutationController
{
    public function __construct(private MessageBusInterface $messageBus) {}

    #[Route('/v1/blogs/general', methods: [Request::METHOD_POST])]
    public function createGeneral(Request $request): JsonResponse
    {
        $user = $request->getUser();
        if (!$user instanceof User || !in_array('ROLE_ROOT', $user->getRoles(), true)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only root can create General blog.');
        }

        $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new CreateGeneralBlogCommand((string) uniqid('op_', true), $user->getId(), (string) ($payload['title'] ?? 'General Blog')));

        return new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blogs/{blogId}/posts', methods: [Request::METHOD_POST])]
    public function createPost(string $blogId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request);
        $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new CreateBlogPostCommand((string) uniqid('op_', true), $user->getId(), $blogId, $payload['content'] ?? null, $payload['filePath'] ?? null));

        return new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/posts/{postId}', methods: [Request::METHOD_PATCH])]
    public function patchPost(string $postId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new PatchBlogPostCommand((string) uniqid('op_', true), $user->getId(), $postId, $payload['content'] ?? null, $payload['filePath'] ?? null));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/posts/{postId}', methods: [Request::METHOD_DELETE])]
    public function deletePost(string $postId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $this->messageBus->dispatch(new DeleteBlogPostCommand((string) uniqid('op_', true), $user->getId(), $postId));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/posts/{postId}/comments', methods: [Request::METHOD_POST])]
    public function createComment(string $postId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new CreateBlogCommentCommand((string) uniqid('op_', true), $user->getId(), $postId, $payload['content'] ?? null, $payload['filePath'] ?? null, $payload['parentCommentId'] ?? null));
        return new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/comments/{commentId}', methods: [Request::METHOD_PATCH])]
    public function patchComment(string $commentId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new PatchBlogCommentCommand((string) uniqid('op_', true), $user->getId(), $commentId, $payload['content'] ?? null, $payload['filePath'] ?? null));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/comments/{commentId}', methods: [Request::METHOD_DELETE])]
    public function deleteComment(string $commentId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $this->messageBus->dispatch(new DeleteBlogCommentCommand((string) uniqid('op_', true), $user->getId(), $commentId));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/comments/{commentId}/reactions', methods: [Request::METHOD_POST])]
    public function createReaction(string $commentId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new CreateBlogReactionCommand((string) uniqid('op_', true), $user->getId(), $commentId, (string) ($payload['type'] ?? 'like')));
        return new JsonResponse(['status' => 'accepted'], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/v1/blog/reactions/{reactionId}', methods: [Request::METHOD_PATCH])]
    public function patchReaction(string $reactionId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $payload = (array) json_decode((string) $request->getContent(), true);
        $this->messageBus->dispatch(new PatchBlogReactionCommand((string) uniqid('op_', true), $user->getId(), $reactionId, (string) ($payload['type'] ?? 'like')));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/blog/reactions/{reactionId}', methods: [Request::METHOD_DELETE])]
    public function deleteReaction(string $reactionId, Request $request): JsonResponse
    {
        $user = $this->requireUser($request); $this->messageBus->dispatch(new DeleteBlogReactionCommand((string) uniqid('op_', true), $user->getId(), $reactionId));
        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    private function requireUser(Request $request): User
    {
        $user = $request->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_UNAUTHORIZED, 'User required.');
        }

        return $user;
    }
}
