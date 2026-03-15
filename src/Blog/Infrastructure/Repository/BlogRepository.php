<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\Repository;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Enum\BlogType;
use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use Doctrine\Persistence\ManagerRegistry;

class BlogRepository extends BaseRepository
{
    protected static string $entityName = Blog::class;
    protected static array $searchColumns = ['id', 'title'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByApplication(Application $application): ?Blog
    {
        $result = $this->findOneBy([
            'application' => $application,
            'type' => BlogType::APPLICATION,
        ]);

        return $result instanceof Blog ? $result : null;
    }

    public function findGeneralBlog(): ?Blog
    {
        $result = $this->findOneBy([
            'type' => BlogType::GENERAL,
        ]);

        return $result instanceof Blog ? $result : null;
    }

    public function findOneByApplicationSlug(string $applicationSlug): ?Blog
    {
        $result = $this->createQueryBuilder('blog')
            ->innerJoin('blog.application', 'application')
            ->addSelect('application')
            ->where('application.slug = :applicationSlug')
            ->andWhere('blog.type = :blogType')
            ->setParameter('applicationSlug', $applicationSlug)
            ->setParameter('blogType', BlogType::APPLICATION)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Blog ? $result : null;
    }
}
