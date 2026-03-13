<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\PatchBlogPostCommand;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\General\Application\Service\CacheInvalidationService;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PatchBlogPostCommandHandler
{
    public function __construct(
        private BlogPostRepository $postRepository,
        private CacheInvalidationService $cacheInvalidationService
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(PatchBlogPostCommand $command): void
    {
        $post = $this->postRepository->find($command->postId);

        if (!$post instanceof BlogPost) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Post not found.');
        }

        if ($post->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only post owner can patch.');
        }

        if ($command->title !== null) {
            $post->setTitle($command->title);
        }

        if ($command->content !== null || $command->sharedUrl !== null) {
            $post->setContent($command->content);
            $post->setSharedUrl($command->sharedUrl);
        }

        if ($command->filePath !== null) {
            $post->setFilePath($command->filePath);
        }

        if ($command->mediaUrls !== null) {
            $post->setMediaUrls($command->mediaUrls);
        }

        if ($command->isPinned !== null) {
            $post->setIsPinned($command->isPinned);
        }

        $this->postRepository->save($post);
        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $post->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($post->getBlog()->getApplication()?->getSlug(), $affectedUserIds);
    }
}
