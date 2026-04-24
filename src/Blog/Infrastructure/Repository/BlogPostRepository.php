<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\General\Infrastructure\Repository\BaseRepository;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_map;
use function array_values;

class BlogPostRepository extends BaseRepository
{
    protected static string $entityName = BlogPost::class;

    protected static array $searchColumns = [
        'id',
        'content',
        'slug',
        'sharedUrl',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return list<BlogPost>
     */
    public function findRootPostsByBlogPaginated(Blog $blog, int $page, int $limit, ?string $tag = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('post')
            ->select('post.id AS id')
            ->where('post.blog = :blog')
            ->andWhere('post.parentPost IS NULL')
            ->orderBy('post.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('blog', $blog->getId(), UuidBinaryOrderedTimeType::NAME);

        if ($tag !== null) {
            $qb
                ->innerJoin('post.tags', 'tag')
                ->andWhere('tag.label = :tagLabel')
                ->setParameter('tagLabel', $tag);
        }

        $idRows = $qb->getQuery()->getArrayResult();

        /** @var list<string> $ids */
        $ids = array_values(
            array_map(
                static fn (array $row): string => (string)$row['id'],
                $idRows
            )
        );

        return $this->findPostsWithDisplayRelationsByIds($ids);
    }

    public function countRootPostsByBlog(Blog $blog, ?string $tag = null): int
    {
        $qb = $this->createQueryBuilder('post')
            ->select('COUNT(post.id)')
            ->where('post.blog = :blog')
            ->andWhere('post.parentPost IS NULL')
            ->setParameter('blog', $blog->getId(), UuidBinaryOrderedTimeType::NAME);

        if ($tag !== null) {
            $qb
                ->innerJoin('post.tags', 'tag')
                ->andWhere('tag.label = :tagLabel')
                ->setParameter('tagLabel', $tag);
        }

        return (int)$qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<BlogPost>
     */
    public function findPostsByAuthorPaginatedWithRelations(User $author, int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;

        $idRows = $this->createQueryBuilder('post')
            ->select('post.id AS id')
            ->where('post.author = :author')
            ->orderBy('post.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->setParameter('author', $author->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getArrayResult();

        /** @var list<string> $ids */
        $ids = array_values(
            array_map(
                static fn (array $row): string => (string)$row['id'],
                $idRows
            )
        );

        return $this->findPostsWithDisplayRelationsByIds($ids);
    }

    public function countPostsByAuthor(User $author): int
    {
        return (int)$this->createQueryBuilder('post')
            ->select('COUNT(post.id)')
            ->where('post.author = :author')
            ->setParameter('author', $author->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneBySlugWithDisplayRelations(string $slug): ?BlogPost
    {
        /** @var list<BlogPost> $posts */
        $posts = $this->createQueryBuilder('post')
            ->leftJoin('post.author', 'author')->addSelect('author')
            ->leftJoin('post.parentPost', 'parent')->addSelect('parent')
            ->leftJoin('post.tags', 'tags')->addSelect('tags')
            ->where('post.slug = :slug')
            ->setParameter('slug', $slug)
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $posts[0] ?? null;
    }

    /**
     * @param list<string> $parentIds
     *
     * @return array<string, array{
     *     count: int,
     *     authors: list<array{
     *         id: string,
     *         username: ?string,
     *         firstName: ?string,
     *         lastName: ?string,
     *         photo: ?string
     *     }>
     * }>
     */
    public function findChildrenSharesSummaryByParentIds(array $parentIds): array
    {
        if ($parentIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('child')
            ->select('IDENTITY(child.parentPost) AS parentId')
            ->addSelect('author.id AS authorId')
            ->addSelect('author.username AS username')
            ->addSelect('author.firstName AS firstName')
            ->addSelect('author.lastName AS lastName')
            ->addSelect('author.photo AS photo')
            ->innerJoin('child.parentPost', 'parent')
            ->innerJoin('child.author', 'author');

        $orX = $qb->expr()->orX();

        foreach ($parentIds as $index => $parentId) {
            $param = 'parent_id_' . $index;
            $orX->add("parent.id = :$param");
            $qb->setParameter($param, $parentId, UuidBinaryOrderedTimeType::NAME);
        }

        $rows = $qb
            ->where($orX)
            ->groupBy('parentId, author.id, author.username, author.firstName, author.lastName, author.photo')
            ->getQuery()
            ->getArrayResult();

        $summary = [];

        foreach ($rows as $row) {
            $parentId = (string)$row['parentId'];

            $summary[$parentId] ??= [
                'count' => 0,
                'authors' => [],
            ];

            $summary[$parentId]['count']++;
            $summary[$parentId]['authors'][] = [
                'id' => (string)$row['authorId'],
                'username' => $row['username'],
                'firstName' => $row['firstName'],
                'lastName' => $row['lastName'],
                'photo' => $row['photo'],
            ];
        }

        return $summary;
    }

    /**
     * @param list<string> $ids
     *
     * @return list<BlogPost>
     */
    private function findPostsWithDisplayRelationsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('post')
            ->distinct()
            ->leftJoin('post.author', 'author')->addSelect('author')
            ->leftJoin('post.parentPost', 'parent')->addSelect('parent')
            ->leftJoin('post.tags', 'tags')->addSelect('tags');

        $orX = $qb->expr()->orX();

        foreach ($ids as $index => $id) {
            $param = 'id_' . $index;
            $orX->add("post.id = :$param");
            $qb->setParameter($param, $id, UuidBinaryOrderedTimeType::NAME);
        }

        /** @var list<BlogPost> $posts */
        $posts = $qb
            ->where($orX)
            ->getQuery()
            ->getResult();

        $postsById = [];
        foreach ($posts as $post) {
            $postsById[$post->getId()] = $post;
        }

        $orderedPosts = [];
        foreach ($ids as $id) {
            if (isset($postsById[$id])) {
                $orderedPosts[] = $postsById[$id];
            }
        }

        return $orderedPosts;
    }
}
