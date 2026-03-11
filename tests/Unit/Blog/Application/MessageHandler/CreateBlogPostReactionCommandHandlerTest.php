<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogPostReactionCommand;
use App\Blog\Application\MessageHandler\CreateBlogPostReactionCommandHandler;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class CreateBlogPostReactionCommandHandlerTest extends TestCase
{
    public function testInvokeCreatesReactionWhenNoneExists(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $postRepository = $this->createMock(BlogPostRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        [$post, $user] = $this->createPostAndUser();

        $postRepository->method('find')->willReturn($post);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::once())
            ->method('findOneByPostAndAuthor')
            ->with($post, $user)
            ->willReturn(null);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(BlogReaction::class));
        $notificationService->expects(self::once())
            ->method('notifyPostReactionCreated')
            ->with($post, $user, BlogReactionType::HEART->value);
        $cacheInvalidationService->expects(self::once())->method('invalidateBlogCaches');

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $handler(new CreateBlogPostReactionCommand('op', 'actor', 'post', BlogReactionType::HEART));
    }

    public function testInvokeUpdatesExistingReactionForSamePostAndAuthor(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $postRepository = $this->createMock(BlogPostRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        [$post, $user] = $this->createPostAndUser();
        $existingReaction = (new BlogReaction())
            ->setPost($post)
            ->setAuthor($user)
            ->setType(BlogReactionType::LIKE);

        $postRepository->method('find')->willReturn($post);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::once())
            ->method('findOneByPostAndAuthor')
            ->with($post, $user)
            ->willReturn($existingReaction);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->with($existingReaction);
        $notificationService->expects(self::never())->method('notifyPostReactionCreated');
        $cacheInvalidationService->expects(self::once())->method('invalidateBlogCaches');

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $handler(new CreateBlogPostReactionCommand('op', 'actor', 'post', BlogReactionType::LAUGH));

        self::assertSame(BlogReactionType::LAUGH, $existingReaction->getType());
    }

    /**
     * @return array{0: BlogPost, 1: User}
     */
    private function createPostAndUser(): array
    {
        $blog = $this->createMock(Blog::class);
        $blog->method('getApplication')->willReturn(null);

        $post = $this->createMock(BlogPost::class);
        $post->method('getBlog')->willReturn($blog);

        $user = $this->createMock(User::class);

        return [$post, $user];
    }
}
