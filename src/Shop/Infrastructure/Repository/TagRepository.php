<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Shop\Domain\Entity\Tag as Entity;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class TagRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return array<int, Entity>
     */
    public function findByApplicationScope(string $applicationSlug, int $limit = 200): array
    {
        /** @var array<int, Entity> $tags */
        $tags = $this->createQueryBuilder('tag')
            ->innerJoin('tag.products', 'product')
            ->innerJoin('product.shop', 'shop')
            ->innerJoin('shop.application', 'application')
            ->andWhere('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->groupBy('tag.id')
            ->orderBy('tag.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $tags;
    }

    public function findOneByIdAndApplicationScope(string $id, string $applicationSlug): ?Entity
    {
        /** @var Entity|null $tag */
        $tag = $this->createQueryBuilder('tag')
            ->innerJoin('tag.products', 'product')
            ->innerJoin('product.shop', 'shop')
            ->innerJoin('shop.application', 'application')
            ->andWhere('tag.id = :id')
            ->andWhere('application.slug = :applicationSlug')
            ->setParameter('id', $id)
            ->setParameter('applicationSlug', $applicationSlug)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $tag;
    }
}
