<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogCommentCommand;
use App\Blog\Application\Message\CreateBlogPostCommand;
use App\Blog\Application\Message\CreateBlogReactionCommand;
use App\Blog\Application\Message\DeleteBlogCommentCommand;
use App\Blog\Application\Message\DeleteBlogPostCommand;
use App\Blog\Application\Message\DeleteBlogReactionCommand;
use App\Blog\Application\Message\PatchBlogCommentCommand;
use App\Blog\Application\Message\PatchBlogPostCommand;
use App\Blog\Application\Message\PatchBlogReactionCommand;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Enum\BlogStatus;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

trait BlogMutationAccessTrait
{
    private function canWritePost(Blog $blog, User $user): bool
    {
        return $blog->getPostStatus() === BlogStatus::OPEN || $blog->getOwner()->getId() === $user->getId();
    }

    private function canWriteComment(Blog $blog, User $user): bool
    {
        return $blog->getCommentStatus() === BlogStatus::OPEN || $blog->getOwner()->getId() === $user->getId();
    }
}

#[AsMessageHandler]
final readonly class CreateBlogPostCommandHandler
{
    use BlogMutationAccessTrait;

    public function __construct(private BlogPostRepository $postRepository, private BlogRepository $blogRepository, private UserRepository $userRepository) {}

    public function __invoke(CreateBlogPostCommand $command): void
    {
        $blog = $this->blogRepository->find($command->blogId);
        $user = $this->userRepository->find($command->actorUserId);
        if (!$blog instanceof Blog || !$user instanceof User) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.'); }
        if (!$this->canWritePost($blog, $user)) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Post creation is restricted to blog owner.'); }
        if ($command->content === null && $command->filePath === null) { throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Post requires content and/or filePath.'); }
        $this->postRepository->save((new BlogPost())->setBlog($blog)->setAuthor($user)->setContent($command->content)->setFilePath($command->filePath));
    }
}

#[AsMessageHandler]
final readonly class PatchBlogPostCommandHandler
{
    public function __construct(private BlogPostRepository $postRepository) {}

    public function __invoke(PatchBlogPostCommand $command): void
    {
        $post = $this->postRepository->find($command->postId);
        if (!$post instanceof BlogPost) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Post not found.'); }
        if ($post->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only post owner can patch.'); }
        $post->setContent($command->content)->setFilePath($command->filePath);
        $this->postRepository->save($post);
    }
}

#[AsMessageHandler]
final readonly class DeleteBlogPostCommandHandler
{
    public function __construct(private BlogPostRepository $postRepository) {}

    public function __invoke(DeleteBlogPostCommand $command): void
    {
        $post = $this->postRepository->find($command->postId);
        if (!$post instanceof BlogPost) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Post not found.'); }
        if ($post->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only post owner can delete.'); }
        $this->postRepository->remove($post);
    }
}

#[AsMessageHandler]
final readonly class CreateBlogCommentCommandHandler
{
    use BlogMutationAccessTrait;

    public function __construct(private BlogCommentRepository $commentRepository, private BlogPostRepository $postRepository, private UserRepository $userRepository) {}

    public function __invoke(CreateBlogCommentCommand $command): void
    {
        $post = $this->postRepository->find($command->postId);
        $user = $this->userRepository->find($command->actorUserId);
        if (!$post instanceof BlogPost || !$user instanceof User) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.'); }
        if (!$this->canWriteComment($post->getBlog(), $user)) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Comments restricted to blog owner.'); }
        if ($command->content === null && $command->filePath === null) { throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Comment requires content and/or filePath.'); }

        $comment = (new BlogComment())->setPost($post)->setAuthor($user)->setContent($command->content)->setFilePath($command->filePath);
        if ($command->parentCommentId !== null) {
            $parent = $this->commentRepository->find($command->parentCommentId);
            if (!$parent instanceof BlogComment) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Parent comment not found.'); }
            $comment->setParent($parent);
        }

        $this->commentRepository->save($comment);
    }
}

#[AsMessageHandler]
final readonly class PatchBlogCommentCommandHandler
{
    public function __construct(private BlogCommentRepository $commentRepository) {}

    public function __invoke(PatchBlogCommentCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);
        if (!$comment instanceof BlogComment) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Comment not found.'); }
        if ($comment->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only comment owner can patch.'); }
        $comment->setContent($command->content)->setFilePath($command->filePath);
        $this->commentRepository->save($comment);
    }
}

#[AsMessageHandler]
final readonly class DeleteBlogCommentCommandHandler
{
    public function __construct(private BlogCommentRepository $commentRepository) {}

    public function __invoke(DeleteBlogCommentCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);
        if (!$comment instanceof BlogComment) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Comment not found.'); }
        if ($comment->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only comment owner can delete.'); }
        $this->commentRepository->remove($comment);
    }
}

#[AsMessageHandler]
final readonly class CreateBlogReactionCommandHandler
{
    public function __construct(private BlogReactionRepository $reactionRepository, private BlogCommentRepository $commentRepository, private UserRepository $userRepository) {}

    public function __invoke(CreateBlogReactionCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);
        $user = $this->userRepository->find($command->actorUserId);
        if (!$comment instanceof BlogComment || !$user instanceof User) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.'); }
        $this->reactionRepository->save((new BlogReaction())->setComment($comment)->setAuthor($user)->setType($command->type));
    }
}

#[AsMessageHandler]
final readonly class PatchBlogReactionCommandHandler
{
    public function __construct(private BlogReactionRepository $reactionRepository) {}

    public function __invoke(PatchBlogReactionCommand $command): void
    {
        $reaction = $this->reactionRepository->find($command->reactionId);
        if (!$reaction instanceof BlogReaction) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.'); }
        if ($reaction->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only reaction owner can patch.'); }
        $reaction->setType($command->type);
        $this->reactionRepository->save($reaction);
    }
}

#[AsMessageHandler]
final readonly class DeleteBlogReactionCommandHandler
{
    public function __construct(private BlogReactionRepository $reactionRepository) {}

    public function __invoke(DeleteBlogReactionCommand $command): void
    {
        $reaction = $this->reactionRepository->find($command->reactionId);
        if (!$reaction instanceof BlogReaction) { throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.'); }
        if ($reaction->getAuthor()->getId() !== $command->actorUserId) { throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only reaction owner can delete.'); }
        $this->reactionRepository->remove($reaction);
    }
}
