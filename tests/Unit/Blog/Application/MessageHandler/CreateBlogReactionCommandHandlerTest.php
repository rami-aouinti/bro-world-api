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
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateBlogCaches')
            ->with('app-slug', ['actor-id', 'comment-author-id', 'post-author-id', 'parent-author-id']);

        $handler = new CreateBlogReactionCommandHandler(
            $reactionRepository,
            $commentRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $handler(new CreateBlogReactionCommand('op', 'actor-id', 'comment', BlogReactionType::HEART));
    }

    public function testInvokeRecoversFromUniqueConstraintAndUpdatesExistingReaction(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $commentRepository = $this->createMock(BlogCommentRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $entityManager = $this->createMock(EntityManager::class);

        [$comment, $user] = $this->createCommentAndUser();
        $existingReaction = (new BlogReaction())
            ->setComment($comment)
            ->setAuthor($user)
            ->setType(BlogReactionType::LIKE);

        $commentRepository->method('find')->willReturn($comment);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::exactly(2))
            ->method('findOneByCommentAndAuthor')
            ->with($comment, $user)
            ->willReturnOnConsecutiveCalls(null, $existingReaction);
        $saveCalls = 0;
        $reactionRepository->expects(self::exactly(2))
            ->method('save')
            ->with(self::callback(static fn (BlogReaction $reaction): bool => $reaction instanceof BlogReaction))
            ->willReturnCallback(function () use (&$saveCalls, $reactionRepository): BlogReactionRepository {
                ++$saveCalls;

                if ($saveCalls === 1) {
                    throw $this->createUniqueConstraintViolationException();
                }

                return $reactionRepository;
            });
        $reactionRepository->expects(self::once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
        $entityManager->expects(self::once())->method('clear');
        $notificationService->expects(self::never())->method('notifyReactionCreated');
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateBlogCaches')
            ->with('app-slug', ['actor-id', 'comment-author-id', 'post-author-id', 'parent-author-id']);

        $handler = new CreateBlogReactionCommandHandler(
            $reactionRepository,
            $commentRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $reactionId = $handler(new CreateBlogReactionCommand('op', 'actor-id', 'comment', BlogReactionType::HEART));

        self::assertSame(BlogReactionType::HEART, $existingReaction->getType());
        self::assertSame($existingReaction->getId(), $reactionId);
    }

    public function testInvokeThrowsConflictWhenRecoveryCannotFindExistingReaction(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $commentRepository = $this->createMock(BlogCommentRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $entityManager = $this->createMock(EntityManager::class);

        [$comment, $user] = $this->createCommentAndUser();

        $commentRepository->method('find')->willReturn($comment);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::exactly(2))
            ->method('findOneByCommentAndAuthor')
            ->with($comment, $user)
            ->willReturn(null);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->willThrowException($this->createUniqueConstraintViolationException());
        $reactionRepository->expects(self::once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
        $entityManager->expects(self::once())->method('clear');
        $notificationService->expects(self::never())->method('notifyReactionCreated');
        $cacheInvalidationService->expects(self::never())->method('invalidateBlogCaches');

        $handler = new CreateBlogReactionCommandHandler(
            $reactionRepository,
            $commentRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        try {
            $handler(new CreateBlogReactionCommand('op', 'actor-id', 'comment', BlogReactionType::HEART));
            self::fail('Expected conflict exception to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_CONFLICT, $exception->getStatusCode());
            self::assertSame('Unable to create blog reaction.', $exception->getMessage());
        }
    }


    private function createUniqueConstraintViolationException(): UniqueConstraintViolationException
    {
        /** @var UniqueConstraintViolationException $exception */
        $exception = (new \ReflectionClass(UniqueConstraintViolationException::class))->newInstanceWithoutConstructor();

        return $exception;
    }

    /**
     * @return array{0: BlogComment, 1: User}
     */
    private function createCommentAndUser(): array
    {
        $application = $this->createMock(Application::class);
        $application->method('getSlug')->willReturn('app-slug');

        $blog = $this->createMock(Blog::class);
        $blog->method('getApplication')->willReturn($application);

        $postAuthor = $this->createMock(User::class);
        $postAuthor->method('getId')->willReturn('post-author-id');

        $post = $this->createMock(BlogPost::class);
        $post->method('getBlog')->willReturn($blog);
        $post->method('getAuthor')->willReturn($postAuthor);

        $parentAuthor = $this->createMock(User::class);
        $parentAuthor->method('getId')->willReturn('parent-author-id');

        $parent = $this->createMock(BlogComment::class);
        $parent->method('getAuthor')->willReturn($parentAuthor);

        $commentAuthor = $this->createMock(User::class);
        $commentAuthor->method('getId')->willReturn('comment-author-id');

        $comment = $this->createMock(BlogComment::class);
        $comment->method('getPost')->willReturn($post);
        $comment->method('getAuthor')->willReturn($commentAuthor);
        $comment->method('getParent')->willReturn($parent);

        $user = $this->createMock(User::class);

        return [$comment, $user];
    }
}
