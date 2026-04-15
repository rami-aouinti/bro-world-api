<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Shop\Domain\Entity\Category as Entity;
use App\Shop\Domain\Entity\Shop;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class CategoryRepository extends BaseRepository
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
    public function findByShop(Shop $shop, int $limit = 200): array
    {
        /** @var array<int, Entity> $categories */
        $categories = $this->createQueryBuilder('category')
            ->andWhere('category.shop = :shop')
            ->setParameter('shop', $shop)
            ->orderBy('category.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $categories;
    }

    public function findOneByIdAndShop(string $id, Shop $shop): ?Entity
    {
        /** @var Entity|null $category */
        $category = $this->createQueryBuilder('category')
            ->andWhere('category.id = :id')
            ->andWhere('category.shop = :shop')
            ->setParameter('id', $id)
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getOneOrNullResult();

        return $category;
    }

    /**
     * @return array<int, Entity>
     */
    public function findGlobalCategories(int $limit = 200): array
    {
        /** @var array<int, Entity> $categories */
        $categories = $this->createQueryBuilder('category')
            ->innerJoin('category.shop', 'shop')
            ->andWhere('shop.isGlobal = true')
            ->orderBy('category.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $categories;
    }
}
