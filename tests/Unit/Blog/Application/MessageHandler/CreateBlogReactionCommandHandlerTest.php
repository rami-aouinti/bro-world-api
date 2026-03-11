<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogReactionCommand;
use App\Blog\Application\MessageHandler\CreateBlogReactionCommandHandler;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\Blog\Infrastructure\Repository\BlogReactionRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

final class CreateBlogReactionCommandHandlerTest extends TestCase
{
    public function testInvokeCreatesReactionWhenNoneExists(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $commentRepository = $this->createMock(BlogCommentRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        [$comment, $user] = $this->createCommentAndUser();

        $commentRepository->method('find')->willReturn($comment);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::once())
            ->method('findOneByCommentAndAuthor')
            ->with($comment, $user)
            ->willReturn(null);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->with(self::isInstanceOf(BlogReaction::class));
        $notificationService->expects(self::once())
            ->method('notifyReactionCreated')
            ->with($comment, $user, BlogReactionType::HEART->value);
        $cacheInvalidationService->expects(self::once())->method('invalidateBlogCaches');

        $handler = new CreateBlogReactionCommandHandler(
            $reactionRepository,
            $commentRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $handler(new CreateBlogReactionCommand('op', 'actor', 'comment', BlogReactionType::HEART));
    }

    public function testInvokeUpdatesExistingReactionForSameCommentAndAuthor(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $commentRepository = $this->createMock(BlogCommentRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        [$comment, $user] = $this->createCommentAndUser();
        $existingReaction = (new BlogReaction())
            ->setComment($comment)
            ->setAuthor($user)
            ->setType(BlogReactionType::LIKE);

        $commentRepository->method('find')->willReturn($comment);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::once())
            ->method('findOneByCommentAndAuthor')
            ->with($comment, $user)
            ->willReturn($existingReaction);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->with($existingReaction);
        $notificationService->expects(self::never())->method('notifyReactionCreated');
        $cacheInvalidationService->expects(self::once())->method('invalidateBlogCaches');

        $handler = new CreateBlogReactionCommandHandler(
            $reactionRepository,
            $commentRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $handler(new CreateBlogReactionCommand('op', 'actor', 'comment', BlogReactionType::LAUGH));

        self::assertSame(BlogReactionType::LAUGH, $existingReaction->getType());
    }

    /**
     * @return array{0: BlogComment, 1: User}
     */
    private function createCommentAndUser(): array
    {
        $blog = $this->createMock(Blog::class);
        $blog->method('getApplication')->willReturn(null);

        $post = $this->createMock(BlogPost::class);
        $post->method('getBlog')->willReturn($blog);

        $comment = $this->createMock(BlogComment::class);
        $comment->method('getPost')->willReturn($post);

        $user = $this->createMock(User::class);

        return [$comment, $user];
    }
}
