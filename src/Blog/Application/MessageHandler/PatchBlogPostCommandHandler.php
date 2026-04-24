<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\PatchBlogPostCommand;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogTagRepository;
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
        private BlogTagRepository $blogTagRepository,
        private BlogNotificationService $blogNotificationService,
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

        if ($command->tagIds !== null) {
            $post->setTags($this->resolveTags($post, $command->tagIds));
        }

        if ($command->isPinned !== null) {
            $post->setIsPinned($command->isPinned);
        }

        $this->postRepository->save($post);
        $this->blogNotificationService->publishBlogEvent($post, 'blog.post.updated', [
            'actorUserId' => $command->actorUserId,
        ]);
        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $post->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($post->getBlog()->getApplication()?->getSlug(), $affectedUserIds);
    }

    /**
     * @param list<string> $tagIds
     *
     * @return list<BlogTag>
     */
    private function resolveTags(BlogPost $post, array $tagIds): array
    {
        if ($tagIds === []) {
            return [];
        }

        $tags = $this->blogTagRepository->findBy(['id' => $tagIds]);

        if (count($tags) !== count($tagIds)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'One or more tags are invalid.');
        }

        foreach ($tags as $tag) {
            if (!$tag instanceof BlogTag || $tag->getBlog()->getId() !== $post->getBlog()->getId()) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Tags must belong to the same blog.');
            }
        }

        return array_values(array_filter($tags, static fn ($tag): bool => $tag instanceof BlogTag));
    }
}
