<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Enum\BlogVisibility;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\User\Domain\Entity\User;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

use function array_map;
use function ceil;
use function max;
use function min;
use function sprintf;

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
    public function getGeneralBlogWithTree(?User $currentUser = null, int $page = 1, int $limit = 20, ?string $tag = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $tagScope = $tag !== null ? sprintf('&tag=%s', $tag) : '';
        $cacheKey = $this->buildBlogCacheKey(sprintf('general?page=%d&limit=%d%s', $page, $limit, $tagScope), $currentUser);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($currentUser, $page, $limit, $tag): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicBlog());
                $item->tag($this->cacheKeyConventionService->tagPublicBlogByApplication(null));
                if ($currentUser !== null) {
                    $item->tag($this->cacheKeyConventionService->tagPrivateBlog($currentUser->getId()));
                }
            }
            $blog = $this->blogRepository->findGeneralBlog();

            if (!$blog instanceof Blog || !$this->canReadBlog($blog, $currentUser)) {
                return [];
            }

            return $this->normalizeBlog($blog, $currentUser, $page, $limit, $tag);
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getByApplicationSlug(string $applicationSlug, ?User $currentUser = null, int $page = 1, int $limit = 20, ?string $tag = null): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));
        $tagScope = $tag !== null ? sprintf('&tag=%s', $tag) : '';
        $cacheKey = $this->buildBlogCacheKey(sprintf('app/%s?page=%d&limit=%d%s', $applicationSlug, $page, $limit, $tagScope), $currentUser);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $currentUser, $page, $limit, $tag): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicBlog());
                $item->tag($this->cacheKeyConventionService->tagPublicBlogByApplication($applicationSlug));
                if ($currentUser !== null) {
                    $item->tag($this->cacheKeyConventionService->tagPrivateBlog($currentUser->getId()));
                }
            }
            $blog = $this->blogRepository->findOneByApplicationSlug($applicationSlug);

            if (!$blog instanceof Blog || !$this->canReadBlog($blog, $currentUser)) {
                return [];
            }

            return $this->normalizeBlog($blog, $currentUser, $page, $limit, $tag);
        });
    }

    public function getPostBySlug(string $slug, ?User $currentUser): array
    {
        $post = $this->blogPostRepository->findOneBySlugWithDisplayRelations($slug);

        if (!$post instanceof BlogPost) {
            return [];
        }

        if (!$this->canReadBlog($post->getBlog(), $currentUser)) {
            return [];
        }

        $childrenSummaryByParent = $post->getParentPost() instanceof BlogPost
            ? []
            : $this->blogPostRepository->findChildrenSharesSummaryByParentIds([$post->getId()]);

        return $this->normalizePost($post, $currentUser, $childrenSummaryByParent);
    }

    public function getMyPosts(User $currentUser, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $posts = $this->blogPostRepository->findPostsByAuthorPaginatedWithRelations($currentUser, $page, $limit);
        $totalItems = $this->blogPostRepository->countPostsByAuthor($currentUser);
        $childrenSummaryByParent = $this->blogPostRepository->findChildrenSharesSummaryByParentIds(
            array_map(static fn (BlogPost $post): string => $post->getId(), $posts),
        );

        return [
            'posts' => array_map(
                fn (BlogPost $post): array => $this->normalizePost($post, $currentUser, $childrenSummaryByParent),
                $posts,
            ),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
        ];
    }

    private function normalizeBlog(Blog $blog, ?User $currentUser, int $page = 1, int $limit = 20, ?string $tag = null): array
    {
        $rootPosts = $this->blogPostRepository->findRootPostsByBlogPaginated($blog, $page, $limit, $tag);
        $totalItems = $this->blogPostRepository->countRootPostsByBlog($blog, $tag);
        $childrenSummaryByParent = $this->blogPostRepository->findChildrenSharesSummaryByParentIds(
            array_map(static fn (BlogPost $post): string => $post->getId(), $rootPosts),
        );

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
            'filter' => [
                'tag' => $tag,
            ],
            'posts' => array_map(
                fn (BlogPost $post): array => $this->normalizePost($post, $currentUser, $childrenSummaryByParent),
                $rootPosts,
            ),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
        ];
    }

    /**
     * @param array<string, array{count: int, authors: list<array{id: string, username: ?string, firstName: ?string, lastName: ?string, photo: ?string}>}> $childrenSummaryByParent
     *
     * @return array<string, mixed>
     */
    private function normalizePost(BlogPost $post, ?User $currentUser, array $childrenSummaryByParent, bool $includeParent = true): array
    {
        $comments = $post->getComments()->toArray();
        $commentTreeByParent = $this->buildCommentTreeByParent($comments);

        return [
            'id' => $post->getId(),
            'slug' => $post->getSlug(),
            'authorId' => $post->getAuthor()->getId(),
            'isAuthor' => $this->isAuthor($post->getAuthor(), $currentUser),
            'author' => $this->normalizeAuthor($post->getAuthor()),
            'title' => $post->getTitle(),
            'content' => $post->getContent(),
            'createdAt' => $post->getCreatedAt()?->format(DATE_ATOM),
            'sharedUrl' => $post->getSharedUrl(),
            'isPinned' => $post->isPinned(),
            'filePath' => $post->getFilePath(),
            'mediaUrls' => $post->getMediaUrls(),
            'tags' => array_map(static fn ($tag): array => [
                'id' => $tag->getId(),
                'label' => $tag->getLabel(),
            ], $post->getTags()->toArray()),
            'parent' => $includeParent && $post->getParentPost() instanceof BlogPost
                ? $this->normalizePost($post->getParentPost(), $currentUser, $childrenSummaryByParent, false)
                : null,
            'reactions' => array_map(fn ($reaction): array => [
                'id' => $reaction->getId(),
                'authorId' => $reaction->getAuthor()->getId(),
                'isAuthor' => $this->isAuthor($reaction->getAuthor(), $currentUser),
                'author' => $this->normalizeAuthor($reaction->getAuthor()),
                'type' => $reaction->getType()->value,
            ], $post->getReactions()->toArray()),
            'comments' => $this->normalizeComments($commentTreeByParent, null, $currentUser),
            'children' => $post->getParentPost() instanceof BlogPost
                ? [
                    'count' => 0,
                    'authors' => [],
                ]
                : $childrenSummaryByParent[$post->getId()] ?? [
                    'count' => 0,
                    'authors' => [],
                ],
        ];
    }

    private function buildBlogCacheKey(string $scope, ?User $currentUser): string
    {
        if ($currentUser === null) {
            return $this->cacheKeyConventionService->buildPublicBlogKey($scope);
        }

        return $this->cacheKeyConventionService->buildPrivateBlogKey($currentUser->getUsername(), $scope);
    }

    private function canReadBlog(Blog $blog, ?User $currentUser): bool
    {
        if ($blog->getVisibility() === BlogVisibility::PUBLIC) {
            return true;
        }

        return $currentUser !== null && $blog->getOwner()->getId() === $currentUser->getId();
    }

    /**
     * @param array<int, BlogComment> $comments
     *
     * @return array<string|null, list<BlogComment>>
     */
    private function buildCommentTreeByParent(array $comments): array
    {
        $tree = [];

        foreach ($comments as $comment) {
            $parentId = $comment->getParent()?->getId();
            $tree[$parentId] ??= [];
            $tree[$parentId][] = $comment;
        }

        return $tree;
    }

    /**
     * @param array<string|null, list<BlogComment>> $commentTreeByParent
     */
    private function normalizeComments(array $commentTreeByParent, ?string $parentId, ?User $currentUser): array
    {
        return array_map(function (BlogComment $comment) use ($commentTreeByParent, $currentUser): array {
            return [
                'id' => $comment->getId(),
                'authorId' => $comment->getAuthor()->getId(),
                'isAuthor' => $this->isAuthor($comment->getAuthor(), $currentUser),
                'author' => $this->normalizeAuthor($comment->getAuthor()),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()?->format(DATE_ATOM),
                'filePath' => $comment->getFilePath(),
                'reactions' => array_map(fn ($reaction): array => [
                    'id' => $reaction->getId(),
                    'authorId' => $reaction->getAuthor()->getId(),
                    'isAuthor' => $this->isAuthor($reaction->getAuthor(), $currentUser),
                    'author' => $this->normalizeAuthor($reaction->getAuthor()),
                    'type' => $reaction->getType()->value,
                ], $comment->getReactions()->toArray()),
                'children' => $this->normalizeComments($commentTreeByParent, $comment->getId(), $currentUser),
            ];
        }, $commentTreeByParent[$parentId] ?? []);
    }

    private function isAuthor(User $author, ?User $currentUser): bool
    {
        return $currentUser !== null && $author->getId() === $currentUser->getId();
    }

    /**
     * @return array<string, mixed>
     */
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
