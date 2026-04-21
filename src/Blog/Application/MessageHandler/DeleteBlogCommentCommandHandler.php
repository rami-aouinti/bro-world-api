<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\DeleteBlogCommentCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\General\Application\Service\CacheInvalidationService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteBlogCommentCommandHandler
{
    public function __construct(
        private BlogCommentRepository $commentRepository,
        private BlogNotificationService $blogNotificationService,
        private CacheInvalidationService $cacheInvalidationService
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(DeleteBlogCommentCommand $command): void
    {
        $comment = $this->commentRepository->find($command->commentId);

        if (!$comment instanceof BlogComment) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Comment not found.');
        }

        if ($comment->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only comment owner can delete.');
        }

        $applicationSlug = $comment->getPost()->getBlog()->getApplication()?->getSlug();
        $this->blogNotificationService->publishBlogEvent($comment->getPost(), 'blog.comment.deleted', [
            'commentId' => $comment->getId(),
            'parentCommentId' => $comment->getParent()?->getId(),
            'actorUserId' => $command->actorUserId,
        ]);
        $this->commentRepository->remove($comment);
        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $comment->getAuthor()->getId(), $comment->getPost()->getAuthor()->getId(), $comment->getParent()?->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($applicationSlug, $affectedUserIds);
    }
}
