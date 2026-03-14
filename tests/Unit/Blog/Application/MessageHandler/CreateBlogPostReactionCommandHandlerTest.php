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
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

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
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateBlogCaches')
            ->with('app-slug', ['actor-id', 'post-author-id']);

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $reactionId = $handler(new CreateBlogPostReactionCommand('op', 'actor-id', 'post', BlogReactionType::HEART));

        self::assertNotSame('', $reactionId);
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
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateBlogCaches')
            ->with('app-slug', ['actor-id', 'post-author-id']);

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $reactionId = $handler(new CreateBlogPostReactionCommand('op', 'actor-id', 'post', BlogReactionType::LAUGH));

        self::assertSame(BlogReactionType::LAUGH, $existingReaction->getType());
        self::assertSame($existingReaction->getId(), $reactionId);
    }

    public function testInvokeRecoversFromUniqueConstraintAndUpdatesExistingReaction(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $postRepository = $this->createMock(BlogPostRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $entityManager = $this->createMock(EntityManager::class);

        [$post, $user] = $this->createPostAndUser();
        $existingReaction = (new BlogReaction())
            ->setPost($post)
            ->setAuthor($user)
            ->setType(BlogReactionType::LIKE);

        $postRepository->method('find')->willReturn($post);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::exactly(2))
            ->method('findOneByPostAndAuthor')
            ->with($post, $user)
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
        $notificationService->expects(self::never())->method('notifyPostReactionCreated');
        $cacheInvalidationService->expects(self::once())
            ->method('invalidateBlogCaches')
            ->with('app-slug', ['actor-id', 'post-author-id']);

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        $reactionId = $handler(new CreateBlogPostReactionCommand('op', 'actor-id', 'post', BlogReactionType::HEART));

        self::assertSame(BlogReactionType::HEART, $existingReaction->getType());
        self::assertSame($existingReaction->getId(), $reactionId);
    }

    public function testInvokeThrowsConflictWhenRecoveryCannotFindExistingReaction(): void
    {
        $reactionRepository = $this->createMock(BlogReactionRepository::class);
        $postRepository = $this->createMock(BlogPostRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $entityManager = $this->createMock(EntityManager::class);

        [$post, $user] = $this->createPostAndUser();

        $postRepository->method('find')->willReturn($post);
        $userRepository->method('find')->willReturn($user);
        $reactionRepository->expects(self::exactly(2))
            ->method('findOneByPostAndAuthor')
            ->with($post, $user)
            ->willReturn(null);
        $reactionRepository->expects(self::once())
            ->method('save')
            ->willThrowException($this->createUniqueConstraintViolationException());
        $reactionRepository->expects(self::once())
            ->method('getEntityManager')
            ->willReturn($entityManager);
        $entityManager->expects(self::once())->method('clear');
        $notificationService->expects(self::never())->method('notifyPostReactionCreated');
        $cacheInvalidationService->expects(self::never())->method('invalidateBlogCaches');

        $handler = new CreateBlogPostReactionCommandHandler(
            $reactionRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        try {
            $handler(new CreateBlogPostReactionCommand('op', 'actor-id', 'post', BlogReactionType::HEART));
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
     * @return array{0: BlogPost, 1: User}
     */
    private function createPostAndUser(): array
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

        $user = $this->createMock(User::class);

        return [$post, $user];
    }
}
