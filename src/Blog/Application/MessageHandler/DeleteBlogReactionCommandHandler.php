<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\DeleteBlogReactionCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteBlogReactionCommandHandler
{
    public function __construct(
        private BlogReactionRepository $reactionRepository,
        private BlogNotificationService $blogNotificationService,
        private CacheInvalidationService $cacheInvalidationService
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(DeleteBlogReactionCommand $command): void
    {
        $reaction = $this->reactionRepository->find($command->reactionId);

        if (!$reaction instanceof BlogReaction) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Reaction not found.');
        }

        if ($reaction->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only reaction owner can delete.');
        }

        $post = $reaction->getPost() ?? $reaction->getComment()?->getPost();
        $applicationSlug = $post?->getBlog()->getApplication()?->getSlug();
        if ($post !== null) {
            $this->blogNotificationService->publishBlogEvent($post, 'blog.reaction.deleted', [
                'reactionId' => $reaction->getId(),
                'commentId' => $reaction->getComment()?->getId(),
                'actorUserId' => $command->actorUserId,
            ]);
        }
        $this->reactionRepository->remove($reaction);
        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $reaction->getAuthor()->getId(), $reaction->getPost()?->getAuthor()->getId(), $reaction->getComment()?->getAuthor()->getId(), $reaction->getComment()?->getParent()?->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($applicationSlug, $affectedUserIds);
    }
}
