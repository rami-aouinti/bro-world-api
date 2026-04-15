<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Shop\Domain\Entity\Product as Entity;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Enum\ProductStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Entity|null find(string $id, LockMode|int|null $lockMode = null, ?int $lockVersion = null, ?string $entityManagerName = null)
 * @method Entity[] findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null, ?string $entityManagerName = null)
 */
class ProductRepository extends BaseRepository
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
    public function findSimilarCandidates(Entity $product, int $limit = 8): array
    {
        $category = $product->getCategory();
        $tagIds = array_map(static fn ($tag): string => $tag->getId(), $product->getTags()->toArray());

        $qb = $this->createQueryBuilder('product')
            ->addSelect('CASE WHEN product.category = :currentCategory THEN 1 ELSE 0 END AS HIDDEN categoryPriority')
            ->addSelect('COUNT(DISTINCT sharedTag.id) AS HIDDEN sharedTagScore')
            ->leftJoin('product.tags', 'sharedTag', 'WITH', 'sharedTag.id IN (:tagIds)')
            ->where('product.id != :currentProductId')
            ->andWhere('product.status = :activeStatus')
            ->groupBy('product.id')
            ->orderBy('categoryPriority', 'DESC')
            ->addOrderBy('sharedTagScore', 'DESC')
            ->addOrderBy('product.isFeatured', 'DESC')
            ->addOrderBy('product.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('currentCategory', $category)
            ->setParameter('tagIds', $tagIds === [] ? ['__no_tag__'] : $tagIds)
            ->setParameter('currentProductId', $product->getId())
            ->setParameter('activeStatus', ProductStatus::ACTIVE);

        if ($category !== null) {
            $qb->andWhere('product.category = :currentCategory OR sharedTag.id IS NOT NULL OR product.isFeatured = true');
        } else {
            $qb->andWhere('sharedTag.id IS NOT NULL OR product.isFeatured = true');
        }

        /** @var array<int, Entity> $results */
        $results = $qb->getQuery()->getResult();

        return $results;
    }

    public function findOneByIdAndShop(string $id, Shop $shop): ?Entity
    {
        /** @var Entity|null $product */
        $product = $this->createQueryBuilder('product')
            ->andWhere('product.id = :id')
            ->andWhere('product.shop = :shop')
            ->setParameter('id', $id)
            ->setParameter('shop', $shop)
            ->getQuery()
            ->getOneOrNullResult();

        return $product;
    }

    public function findOneGlobalById(string $id): ?Entity
    {
        /** @var Entity|null $product */
        $product = $this->createQueryBuilder('product')
            ->innerJoin('product.shop', 'shop')
            ->andWhere('product.id = :id')
            ->andWhere('shop.isGlobal = true')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        return $product;
    }
}
