<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\DeleteBlogPostCommand;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\General\Application\Service\CacheInvalidationService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class DeleteBlogPostCommandHandler
{
    public function __construct(
        private BlogPostRepository $postRepository,
        private CacheInvalidationService $cacheInvalidationService
    ) {
    }

    public function __invoke(DeleteBlogPostCommand $command): void
    {
        $post = $this->postRepository->find($command->postId);

        if (!$post instanceof BlogPost) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Post not found.');
        }

        if ($post->getAuthor()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only post owner can delete.');
        }

        $applicationSlug = $post->getBlog()->getApplication()?->getSlug();
        $this->postRepository->remove($post);
        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $post->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($applicationSlug, $affectedUserIds);
    }
}
