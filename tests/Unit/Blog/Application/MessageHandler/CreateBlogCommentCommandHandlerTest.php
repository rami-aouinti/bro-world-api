<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\MessageHandler;

use App\Blog\Application\Message\CreateBlogCommentCommand;
use App\Blog\Application\MessageHandler\CreateBlogCommentCommandHandler;
use App\Blog\Application\Service\BlogNotificationService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Enum\BlogStatus;
use App\Blog\Infrastructure\Repository\BlogCommentRepository;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\General\Application\Service\CacheInvalidationService;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CreateBlogCommentCommandHandlerTest extends TestCase
{
    public function testInvokeRejectsParentFromAnotherPost(): void
    {
        $commentRepository = $this->createMock(BlogCommentRepository::class);
        $postRepository = $this->createMock(BlogPostRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $notificationService = $this->createMock(BlogNotificationService::class);
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        $targetPost = $this->createPost('post-target');
        $parentPost = $this->createPost('post-parent');

        $user = $this->createMock(User::class);

        $parent = $this->createMock(BlogComment::class);
        $parent->method('getPost')->willReturn($parentPost);

        $postRepository->method('find')->with('post-target')->willReturn($targetPost);
        $userRepository->method('find')->with('actor')->willReturn($user);
        $commentRepository->method('find')->with('parent-comment')->willReturn($parent);
        $commentRepository->expects(self::never())->method('save');
        $notificationService->expects(self::never())->method('notifyCommentCreated');
        $cacheInvalidationService->expects(self::never())->method('invalidateBlogCaches');

        $handler = new CreateBlogCommentCommandHandler(
            $commentRepository,
            $postRepository,
            $userRepository,
            $notificationService,
            $cacheInvalidationService,
        );

        try {
            $handler(new CreateBlogCommentCommand('op', 'actor', 'post-target', 'content', null, 'parent-comment'));
            self::fail('Expected HttpException was not thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, $exception->getStatusCode());
            self::assertSame('Parent comment must belong to the same post.', $exception->getMessage());
        }
    }

    private function createPost(string $id): BlogPost
    {
        $owner = $this->createMock(User::class);
        $owner->method('getId')->willReturn('owner-id');

        $blog = $this->createMock(Blog::class);
        $blog->method('getCommentStatus')->willReturn(BlogStatus::OPEN);
        $blog->method('getOwner')->willReturn($owner);

        $post = $this->createMock(BlogPost::class);
        $post->method('getId')->willReturn($id);
        $post->method('getBlog')->willReturn($blog);

        return $post;
    }
}
