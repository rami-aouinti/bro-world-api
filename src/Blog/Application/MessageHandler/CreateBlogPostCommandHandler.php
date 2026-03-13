<?php

declare(strict_types=1);

namespace App\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogPostCommand;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateBlogPostCommandHandler
{
    use BlogMutationAccessTrait;

    public function __construct(
        private BlogPostRepository $postRepository,
        private BlogRepository $blogRepository,
        private UserRepository $userRepository,
        private CacheInvalidationService $cacheInvalidationService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function __invoke(CreateBlogPostCommand $command): string
    {
        $blog = $this->blogRepository->find($command->blogId);
        $user = $this->userRepository->find($command->actorUserId);

        if (!$blog instanceof Blog || !$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        if (!$this->canWritePost($blog, $user)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Post creation is restricted to blog owner.');
        }

        if ($this->postRepository->findOneBy(['slug' => $command->slug]) instanceof BlogPost) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Slug already exists.');
        }

        $parentPost = null;
        if ($command->parentPostId !== null) {
            $parentPost = $this->postRepository->find($command->parentPostId);
            if (!$parentPost instanceof BlogPost || $parentPost->getBlog()->getId() !== $blog->getId()) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Parent post does not belong to this blog.');
            }
        }

        if ($command->content === null && $command->filePath === null && $command->sharedUrl === null && $command->mediaUrls === []) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Post requires content, url and/or media.');
        }

        $post = new BlogPost();

        $this->postRepository->save($post
            ->setBlog($blog)
            ->setAuthor($user)
            ->setTitle($command->title)
            ->setSlug($command->slug)
            ->setContent($command->content)
            ->setFilePath($command->filePath)
            ->setMediaUrls($command->mediaUrls)
            ->setSharedUrl($command->sharedUrl)
            ->setParentPost($parentPost)
            ->setIsPinned($command->isPinned));

        $affectedUserIds = array_values(array_filter(array_unique([$command->actorUserId, $blog->getOwner()->getId(), $parentPost?->getAuthor()->getId()]), static fn (?string $userId): bool => $userId !== null && $userId !== ''));
        $this->cacheInvalidationService->invalidateBlogCaches($blog->getApplication()?->getSlug(), $affectedUserIds);

        return $post->getId();
    }
}
