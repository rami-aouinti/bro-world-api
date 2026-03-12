<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\User\Domain\Entity\User;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function array_filter;
use function array_map;
use function array_slice;
use function array_values;
use function ceil;
use function count;
use function max;
use function min;
use function sprintf;
use function usort;

final readonly class BlogReadService
{
    public function __construct(
        private BlogRepository $blogRepository,
        private BlogPostRepository $blogPostRepository,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getGeneralBlogWithTree(?User $currentUser = null, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $cacheKey = $this->buildBlogCacheKey(sprintf('general?page=%d&limit=%d', $page, $limit), $currentUser);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currentUser, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicBlog());
                $item->tag($this->cacheKeyConventionService->tagPublicBlogByApplication(null));
                if ($currentUser !== null) {
                    $item->tag($this->cacheKeyConventionService->tagPrivateBlog($currentUser->getId()));
                }
            }
            $blog = $this->blogRepository->findGeneralBlog();

            return $blog instanceof Blog ? $this->normalizeBlog($blog, $currentUser, $page, $limit) : [];
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getByApplicationSlug(string $applicationSlug, ?User $currentUser = null): array
    {
        $cacheKey = $this->buildBlogCacheKey('app/' . $applicationSlug, $currentUser);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $currentUser): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicBlog());
                $item->tag($this->cacheKeyConventionService->tagPublicBlogByApplication($applicationSlug));
                if ($currentUser !== null) {
                    $item->tag($this->cacheKeyConventionService->tagPrivateBlog($currentUser->getId()));
                }
            }
            $blog = $this->blogRepository->findOneByApplicationSlug($applicationSlug);

            return $blog instanceof Blog ? $this->normalizeBlog($blog, $currentUser) : [];
        });
    }

    public function getPostBySlug(string $slug, ?User $currentUser): array
    {
        $post = $this->blogPostRepository->findOneBy(['slug' => $slug]);

        if (!$post instanceof BlogPost) {
            return [];
        }

        return $this->normalizePost($post, $post->getBlog()->getPosts()->toArray(), $currentUser);
    }

    public function getMyPosts(User $currentUser, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        /** @var list<BlogPost> $posts */
        $posts = $this->blogPostRepository->findBy(['author' => $currentUser]);
        usort($posts, static fn (BlogPost $left, BlogPost $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());

        $totalItems = count($posts);
        $offset = ($page - 1) * $limit;
        $posts = array_slice($posts, $offset, $limit);

        return [
            'posts' => array_map(fn (BlogPost $post): array => $this->normalizePost($post, $post->getBlog()->getPosts()->toArray(), $currentUser), $posts),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
        ];
    }

    private function normalizeBlog(Blog $blog, ?User $currentUser, int $page = 1, int $limit = 20): array
    {
        /** @var list<BlogPost> $posts */
        $posts = $blog->getPosts()->toArray();

        usort($posts, static fn (BlogPost $left, BlogPost $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());
        $rootPosts = array_values(array_filter($posts, static fn (BlogPost $post): bool => $post->getParentPost() === null));

        $totalItems = count($rootPosts);
        $offset = ($page - 1) * $limit;
        $rootPosts = array_slice($rootPosts, $offset, $limit);

        return [
            'id' => $blog->getId(),
            'title' => $blog->getTitle(),
            'slug' => $blog->getSlug(),
            'description' => $blog->getDescription(),
            'type' => $blog->getType()->value,
            'postStatus' => $blog->getPostStatus()->value,
            'commentStatus' => $blog->getCommentStatus()->value,
            'visibility' => $blog->getVisibility()->value,
            'applicationSlug' => $blog->getApplication()?->getSlug(),
            'posts' => array_map(fn (BlogPost $post): array => $this->normalizePost($post, $posts, $currentUser), $rootPosts),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
        ];
    }

    /**
     * @param list<BlogPost> $allPosts
     */
    private function normalizePost(BlogPost $post, array $allPosts, ?User $currentUser): array
    {
        $postId = $post->getId();

        return [
            'id' => $postId,
            'slug' => $post->getSlug(),
            'authorId' => $post->getAuthor()->getId(),
            'isAuthor' => $this->isAuthor($post->getAuthor(), $currentUser),
            'author' => $this->normalizeAuthor($post->getAuthor()),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'sharedUrl' => $post->getSharedUrl(),
            'isPinned' => $post->isPinned(),
            'filePath' => $post->getFilePath(),
            'mediaUrls' => $post->getMediaUrls(),
            'parentPostId' => $post->getParentPost()?->getId(),
            'reactions' => array_map(fn ($reaction): array => [
                'id' => $reaction->getId(),
                'authorId' => $reaction->getAuthor()->getId(),
                'isAuthor' => $this->isAuthor($reaction->getAuthor(), $currentUser),
                'author' => $this->normalizeAuthor($reaction->getAuthor()),
                'type' => $reaction->getType()->value,
            ], $post->getReactions()->toArray()),
            'comments' => $this->normalizeComments($post->getComments()->toArray(), null, $currentUser),
            'children' => $this->normalizeChildrenPosts($allPosts, $postId, $currentUser),
        ];
    }

    /**
     * @param list<BlogPost> $allPosts
     *
     * @return list<array<string, mixed>>
     */
    private function normalizeChildrenPosts(array $allPosts, string $parentPostId, ?User $currentUser): array
    {
        $children = array_values(array_filter($allPosts, static fn (BlogPost $post): bool => $post->getParentPost()?->getId() === $parentPostId));
        usort($children, static fn (BlogPost $left, BlogPost $right): int => $right->getCreatedAt() <=> $left->getCreatedAt());

        return array_map(fn (BlogPost $child): array => $this->normalizePost($child, $allPosts, $currentUser), $children);
    }

    private function buildBlogCacheKey(string $scope, ?User $currentUser): string
    {
        if ($currentUser === null) {
            return $this->cacheKeyConventionService->buildPublicBlogKey($scope);
        }

        return $this->cacheKeyConventionService->buildPrivateBlogKey($currentUser->getUsername(), $scope);
    }

    /**
     * @param array<int, BlogComment> $comments
     */
    private function normalizeComments(array $comments, ?string $parentId, ?User $currentUser): array
    {
        $filtered = array_filter($comments, static fn (BlogComment $comment): bool => $comment->getParent()?->getId() === $parentId);

        return array_map(function (BlogComment $comment) use ($comments, $currentUser): array {
            return [
                'id' => $comment->getId(),
                'authorId' => $comment->getAuthor()->getId(),
                'isAuthor' => $this->isAuthor($comment->getAuthor(), $currentUser),
                'author' => $this->normalizeAuthor($comment->getAuthor()),
                'content' => $comment->getContent(),
                'filePath' => $comment->getFilePath(),
                'reactions' => array_map(fn ($reaction): array => [
                    'id' => $reaction->getId(),
                    'authorId' => $reaction->getAuthor()->getId(),
                    'isAuthor' => $this->isAuthor($reaction->getAuthor(), $currentUser),
                    'author' => $this->normalizeAuthor($reaction->getAuthor()),
                    'type' => $reaction->getType()->value,
                ], $comment->getReactions()->toArray()),
                'children' => $this->normalizeComments($comments, $comment->getId(), $currentUser),
            ];
        }, array_values($filtered));
    }

    private function isAuthor(User $author, ?User $currentUser): bool
    {
        return $currentUser !== null && $author->getId() === $currentUser->getId();
    }

    private function normalizeAuthor(User $author): array
    {
        return [
            'id' => $author->getId(),
            'username' => $author->getUsername(),
            'firstName' => $author->getFirstName(),
            'lastName' => $author->getLastName(),
            'photo' => $author->getPhoto(),
        ];
    }
}
