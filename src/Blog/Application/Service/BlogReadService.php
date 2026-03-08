<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final readonly class BlogReadService
{
    public function __construct(private BlogRepository $blogRepository, private CacheInterface $cache, private ElasticsearchServiceInterface $elasticsearchService) {}

    /** @throws InvalidArgumentException */
    public function getGeneralBlogWithTree(): array
    {
        return $this->cache->get('blog_general_tree', function (ItemInterface $item): array {
            $item->expiresAfter(120);
            $blog = $this->blogRepository->findGeneralBlog();

            return $blog instanceof Blog ? $this->normalizeBlog($blog) : [];
        });
    }

    /** @throws InvalidArgumentException */
    public function getByApplicationSlug(string $applicationSlug): array
    {
        return $this->cache->get('blog_app_' . $applicationSlug, function (ItemInterface $item) use ($applicationSlug): array {
            $item->expiresAfter(120);
            $blog = $this->blogRepository->findOneBy(['application.slug' => $applicationSlug]);
            if (!$blog instanceof Blog) {
                $blog = $this->blogRepository->createQueryBuilder('b')
                    ->leftJoin('b.application', 'a')
                    ->andWhere('a.slug = :slug')
                    ->setParameter('slug', $applicationSlug)
                    ->getQuery()
                    ->getOneOrNullResult();
            }

            return $blog instanceof Blog ? $this->normalizeBlog($blog) : [];
        });
    }

    private function normalizeBlog(Blog $blog): array
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
                'content' => $p->getContent(),
                'filePath' => $p->getFilePath(),
                'comments' => $this->normalizeComments($p->getComments()->toArray(), null),
            ], $blog->getPosts()->toArray()),
        ];
    }

    /** @param array<int, BlogComment> $comments */
    private function normalizeComments(array $comments, ?string $parentId): array
    {
        $filtered = array_filter($comments, static fn (BlogComment $comment): bool => $comment->getParent()?->getId() === $parentId);

        return array_map(function (BlogComment $comment) use ($comments): array {
            return [
                'id' => $comment->getId(),
                'authorId' => $comment->getAuthor()->getId(),
                'content' => $comment->getContent(),
                'filePath' => $comment->getFilePath(),
                'reactions' => array_map(static fn ($r): array => ['id' => $r->getId(), 'authorId' => $r->getAuthor()->getId(), 'type' => $r->getType()], $comment->getReactions()->toArray()),
                'children' => $this->normalizeComments($comments, $comment->getId()),
            ];
        }, array_values($filtered));
    }
}
