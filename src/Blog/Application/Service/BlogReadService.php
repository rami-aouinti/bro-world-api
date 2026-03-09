<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class BlogReadService
{
    public function __construct(private BlogRepository $blogRepository, private CacheInterface $cache, private ElasticsearchServiceInterface $elasticsearchService) {}

    /** @throws InvalidArgumentException */
    public function getGeneralBlogWithTree(?User $currentUser = null): array
    {
        $userId = $currentUser?->getId() ?? 'anonymous';

        return $this->cache->get('blog_general_tree_' . $userId, function (ItemInterface $item) use ($currentUser): array {
            $item->expiresAfter(120);
            $blog = $this->blogRepository->findGeneralBlog();

            return $blog instanceof Blog ? $this->normalizeBlog($blog, $currentUser) : [];
        });
    }

    /** @throws InvalidArgumentException */
    public function getByApplicationSlug(string $applicationSlug, ?User $currentUser = null): array
    {
        $userId = $currentUser?->getId() ?? 'anonymous';

        return $this->cache->get('blog_app_' . $applicationSlug . '_' . $userId, function (ItemInterface $item) use ($applicationSlug, $currentUser): array {
            $item->expiresAfter(120);
            $blog = $this->blogRepository->findOneByApplicationSlug($applicationSlug);

            return $blog instanceof Blog ? $this->normalizeBlog($blog, $currentUser) : [];
        });
    }

    private function normalizeBlog(Blog $blog, ?User $currentUser): array
    {
        return [
            'id' => $blog->getId(),
            'title' => $blog->getTitle(),
            'type' => $blog->getType()->value,
            'postStatus' => $blog->getPostStatus()->value,
            'commentStatus' => $blog->getCommentStatus()->value,
            'applicationSlug' => $blog->getApplication()?->getSlug(),
            'posts' => array_map(fn ($p): array => [
                'id' => $p->getId(),
                'authorId' => $p->getAuthor()->getId(),
                'isAuthor' => $this->isAuthor($p->getAuthor(), $currentUser),
                'author' => $this->normalizeAuthor($p->getAuthor()),
                'content' => $p->getContent(),
                'filePath' => $p->getFilePath(),
                'comments' => $this->normalizeComments($p->getComments()->toArray(), null, $currentUser),
            ], $blog->getPosts()->toArray()),
        ];
    }

    /** @param array<int, BlogComment> $comments */
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
                'reactions' => array_map(fn ($r): array => [
                    'id' => $r->getId(),
                    'authorId' => $r->getAuthor()->getId(),
                    'isAuthor' => $this->isAuthor($r->getAuthor(), $currentUser),
                    'author' => $this->normalizeAuthor($r->getAuthor()),
                    'type' => $r->getType(),
                ], $comment->getReactions()->toArray()),
                'children' => $this->normalizeComments($comments, $comment->getId(), $currentUser),
            ];
        }, array_values($filtered));
    }

    private function isAuthor(User $author, ?User $currentUser): bool
    {
        return null !== $currentUser && $author->getId() === $currentUser->getId();
    }

    private function normalizeAuthor(User $author): array
    {
        return [
            'username' => $author->getUsername(),
            'firstName' => $author->getFirstName(),
            'lastName' => $author->getLastName(),
            'photo' => $author->getPhoto(),
        ];
    }
}
