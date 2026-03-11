<?php

declare(strict_types=1);

namespace App\Tests\Unit\Blog\Application\Service;

use App\Blog\Application\Service\BlogReadService;
use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Blog\Domain\Enum\BlogStatus;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Domain\Enum\BlogVisibility;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Contracts\Cache\CacheInterface;

final class BlogReadServiceTest extends TestCase
{
    public function testNormalizeCommentsBuildsTreePreservesOrderAndComputesAuthors(): void
    {
        $service = $this->createService();
        $currentUser = $this->mockUser('u-current', 'john-user');
        $otherUser = $this->mockUser('u-other', 'alice-user');

        $parentB = $this->mockComment('c-parent-b', $otherUser, null, []);
        $childB1 = $this->mockComment('c-child-b1', $currentUser, 'c-parent-b', []);
        $parentA = $this->mockComment('c-parent-a', $currentUser, null, []);
        $childA1 = $this->mockComment('c-child-a1', $otherUser, 'c-parent-a', []);
        $grandChildA1 = $this->mockComment('c-grand-a1', $currentUser, 'c-child-a1', []);

        $comments = [$childB1, $parentB, $grandChildA1, $parentA, $childA1];

        /** @var array<int, array<string, mixed>> $normalized */
        $normalized = $this->invokePrivate($service, 'normalizeComments', [$comments, null, $currentUser]);

        self::assertSame(['c-parent-b', 'c-parent-a'], array_column($normalized, 'id'));
        self::assertFalse($normalized[0]['isAuthor']);
        self::assertTrue($normalized[1]['isAuthor']);

        self::assertSame('c-child-b1', $normalized[0]['children'][0]['id']);
        self::assertTrue($normalized[0]['children'][0]['isAuthor']);

        self::assertSame('c-child-a1', $normalized[1]['children'][0]['id']);
        self::assertSame('c-grand-a1', $normalized[1]['children'][0]['children'][0]['id']);
        self::assertTrue($normalized[1]['children'][0]['children'][0]['isAuthor']);
    }

    public function testNormalizeBlogNormalizesPostAndCommentTrees(): void
    {
        $service = $this->createService();
        $currentUser = $this->mockUser('u-current', 'john-user');
        $otherUser = $this->mockUser('u-other', 'alice-user');

        $reaction = $this->createMock(BlogReaction::class);
        $reaction->method('getId')->willReturn('r-1');
        $reaction->method('getAuthor')->willReturn($currentUser);
        $reaction->method('getType')->willReturn(BlogReactionType::LAUGH);

        $rootComment = $this->mockComment('c-root', $otherUser, null, [$reaction]);
        $childComment = $this->mockComment('c-child', $currentUser, 'c-root', []);

        $postReaction = $this->createMock(BlogReaction::class);
        $postReaction->method('getId')->willReturn('pr-1');
        $postReaction->method('getAuthor')->willReturn($otherUser);
        $postReaction->method('getType')->willReturn(BlogReactionType::HEART);

        $post = $this->createMock(BlogPost::class);
        $post->method('getId')->willReturn('p-1');
        $post->method('getAuthor')->willReturn($currentUser);
        $post->method('getTitle')->willReturn('Post title');
        $post->method('getContent')->willReturn('Post content');
        $post->method('isPinned')->willReturn(true);
        $post->method('getFilePath')->willReturn('/uploads/post.png');
        $post->method('getReactions')->willReturn(new ArrayCollection([$postReaction]));
        $post->method('getComments')->willReturn(new ArrayCollection([$childComment, $rootComment]));

        $blog = $this->createMock(Blog::class);
        $blog->method('getId')->willReturn('b-1');
        $blog->method('getTitle')->willReturn('Blog title');
        $blog->method('getSlug')->willReturn('blog-slug');
        $blog->method('getDescription')->willReturn('Blog description');
        $blog->method('getType')->willReturn(BlogType::APPLICATION);
        $blog->method('getPostStatus')->willReturn(BlogStatus::OPEN);
        $blog->method('getCommentStatus')->willReturn(BlogStatus::OPEN);
        $blog->method('getVisibility')->willReturn(BlogVisibility::PUBLIC);
        $blog->method('getApplication')->willReturn(null);
        $blog->method('getPosts')->willReturn(new ArrayCollection([$post]));

        /** @var array<string, mixed> $normalized */
        $normalized = $this->invokePrivate($service, 'normalizeBlog', [$blog, $currentUser]);

        self::assertSame('b-1', $normalized['id']);
        self::assertCount(1, $normalized['posts']);
        self::assertTrue($normalized['posts'][0]['isAuthor']);
        self::assertSame('heart', $normalized['posts'][0]['reactions'][0]['type']);
        self::assertFalse($normalized['posts'][0]['reactions'][0]['isAuthor']);
        self::assertSame('c-root', $normalized['posts'][0]['comments'][0]['id']);
        self::assertSame('c-child', $normalized['posts'][0]['comments'][0]['children'][0]['id']);
        self::assertSame('laugh', $normalized['posts'][0]['comments'][0]['reactions'][0]['type']);
        self::assertTrue($normalized['posts'][0]['comments'][0]['reactions'][0]['isAuthor']);
    }

    private function createService(): BlogReadService
    {
        return new BlogReadService(
            $this->createMock(BlogRepository::class),
            $this->createMock(CacheInterface::class),
            new CacheKeyConventionService(),
        );
    }

    /**
     * @param array<int, BlogReaction> $reactions
     */
    private function mockComment(string $id, User $author, ?string $parentId, array $reactions): BlogComment
    {
        $comment = $this->createMock(BlogComment::class);
        $parent = null;
        if ($parentId !== null) {
            $parent = $this->createMock(BlogComment::class);
            $parent->method('getId')->willReturn($parentId);
        }

        $comment->method('getId')->willReturn($id);
        $comment->method('getAuthor')->willReturn($author);
        $comment->method('getParent')->willReturn($parent);
        $comment->method('getContent')->willReturn('Comment ' . $id);
        $comment->method('getFilePath')->willReturn(null);
        $comment->method('getReactions')->willReturn(new ArrayCollection($reactions));

        return $comment;
    }

    private function mockUser(string $id, string $username): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($username);
        $user->method('getFirstName')->willReturn('First');
        $user->method('getLastName')->willReturn('Last');
        $user->method('getPhoto')->willReturn('/uploads/' . $username . '.png');

        return $user;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function invokePrivate(object $service, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionClass($service);
        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        return $reflectionMethod->invokeArgs($service, $arguments);
    }
}
