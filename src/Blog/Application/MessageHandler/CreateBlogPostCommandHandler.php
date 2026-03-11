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
    public function __invoke(CreateBlogPostCommand $command): void
    {
        $blog = $this->blogRepository->find($command->blogId);
        $user = $this->userRepository->find($command->actorUserId);

        if (!$blog instanceof Blog || !$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        if (!$this->canWritePost($blog, $user)) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Post creation is restricted to blog owner.');
        }

        if ($command->content === null && $command->filePath === null) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Post requires content and/or filePath.');
        }

        $this->postRepository->save(new BlogPost()
            ->setBlog($blog)
            ->setAuthor($user)
            ->setTitle($command->title)
            ->setContent($command->content)
            ->setFilePath($command->filePath)
            ->setIsPinned($command->isPinned));

        $this->cacheInvalidationService->invalidateBlogCaches($blog->getApplication()?->getSlug(), $command->actorUserId);
    }
}
