<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogCommentCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBlogCommentCommandHandler
{
    use BlogMutationAccessTrait;

    public function __construct(
        private BlogCommentRepository $commentRepository,
        private BlogPostRepository $postRepository,
        private UserRepository $userRepository,
        private BlogNotificationService $blogNotificationService,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(CreateBlogCommentCommand $command): string
    {
        $post = $this->postRepository->find($command->postId);
        $user = $this->userRepository->find($command->actorUserId);

        if (!$post instanceof BlogPost || !$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        if (!$this->canWriteComment($post->getBlog(), $user)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Comments restricted to blog owner.');
        }

        if ($command->content === null && $command->filePath === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Comment requires content and/or filePath.');
        }

        $comment = (new BlogComment())
            ->setPost($post)
            ->setAuthor($user)
            ->setContent($command->content)
            ->setFilePath($command->filePath);

        if ($command->parentCommentId !== null) {
            $parent = $this->commentRepository->find($command->parentCommentId);
            if (!$parent instanceof BlogComment) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Parent comment not found.');
            }

            if ($parent->getPost()->getId() !== $post->getId()) {
                throw new HttpException(
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                    'Parent comment must belong to the same post.',
                );
            }

            $comment->setParent($parent);
        }

        $this->commentRepository->save($comment);
        $this->blogNotificationService->notifyCommentCreated($comment);

        $affectedUserIds = array_values(array_filter(array_unique([
            $command->actorUserId,
            $post->getAuthor()->getId(),
            $comment->getParent()?->getAuthor()->getId(),
        ]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));

        $this->cacheInvalidationService->invalidateBlogCaches($post->getBlog()->getApplication()?->getSlug(), $affectedUserIds);

        return $comment->getId();
    }
}
