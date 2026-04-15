<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Shop\Application\Projection\ShopProductProjection;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Throwable;

readonly class ProductListService
{
    public function __construct(
        private ProductRepository $productRepository,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function getList(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'name' => trim((string)$request->query->get('name', '')),
            'category' => trim((string)$request->query->get('category', '')),
            'status' => trim((string)$request->query->get('status', '')),
            'minPrice' => max(0, (float)$request->query->get('minPrice', 0)),
            'maxPrice' => max(0, (float)$request->query->get('maxPrice', 0)),
            'minPromotion' => max(0, min(100, (int)$request->query->get('promotion', $request->query->get('minPromotion', 0)))),
        ];

        $cacheKey = $this->cacheKeyConventionService->buildShopProductListKey($page, $limit, $filters);

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->shopProductListTag());
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds === []) {
                return [
                    'items' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'totalItems' => 0,
                        'totalPages' => 0,
                    ],
                ];
            }

            $qb = $this->productRepository->createQueryBuilder('product')
                ->leftJoin('product.category', 'category')
                ->leftJoin('product.tags', 'tag')
                ->addSelect('category', 'tag')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->orderBy('product.createdAt', 'DESC');

            if ($filters['name'] !== '') {
                $qb->andWhere('LOWER(product.name) LIKE LOWER(:name)')->setParameter('name', '%' . $filters['name'] . '%');
            }

            if ($filters['category'] !== '') {
                $qb->andWhere('LOWER(category.name) LIKE LOWER(:category)')->setParameter('category', '%' . $filters['category'] . '%');
            }

            if ($filters['status'] !== '') {
                $qb->andWhere('product.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['minPrice'] > 0) {
                $qb->andWhere('product.price >= :minPrice')->setParameter('minPrice', (int)round($filters['minPrice'] * 100));
            }
            if ($filters['maxPrice'] > 0) {
                $qb->andWhere('product.price <= :maxPrice')->setParameter('maxPrice', (int)round($filters['maxPrice'] * 100));
            }
            if ($filters['minPromotion'] > 0) {
                $qb->andWhere('product.promotionPercentage >= :minPromotion')->setParameter('minPromotion', $filters['minPromotion']);
            }

            if ($esIds !== null) {
                $qb->andWhere('product.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $items = array_map(static fn (Product $product): array => self::serializeProduct($product), $qb->getQuery()->getResult());

            $countQb = $this->productRepository->createQueryBuilder('product')->select('COUNT(product.id)')
                ->leftJoin('product.category', 'category');

            if ($filters['name'] !== '') {
                $countQb->andWhere('LOWER(product.name) LIKE LOWER(:name)')->setParameter('name', '%' . $filters['name'] . '%');
            }
            if ($filters['category'] !== '') {
                $countQb->andWhere('LOWER(category.name) LIKE LOWER(:category)')->setParameter('category', '%' . $filters['category'] . '%');
            }
            if ($filters['status'] !== '') {
                $countQb->andWhere('product.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['minPrice'] > 0) {
                $countQb->andWhere('product.price >= :minPrice')->setParameter('minPrice', (int)round($filters['minPrice'] * 100));
            }
            if ($filters['maxPrice'] > 0) {
                $countQb->andWhere('product.price <= :maxPrice')->setParameter('maxPrice', (int)round($filters['maxPrice'] * 100));
            }
            if ($filters['minPromotion'] > 0) {
                $countQb->andWhere('product.promotionPercentage >= :minPromotion')->setParameter('minPromotion', $filters['minPromotion']);
            }
            if ($esIds !== null) {
                $countQb->andWhere('product.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'module' => 'shop',
                ],
            ];
        });

        $result['meta']['filters'] = array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== 0 && $value !== 0.0);

        return $result;
    }

    /**
     * @return array<string, mixed>
     * @throws \JsonException
     * @throws InvalidArgumentException
     */
    public function getGlobalList(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'name' => trim((string)$request->query->get('name', '')),
            'category' => trim((string)$request->query->get('category', '')),
            'status' => trim((string)$request->query->get('status', '')),
            'minPrice' => max(0, (float)$request->query->get('minPrice', 0)),
            'maxPrice' => max(0, (float)$request->query->get('maxPrice', 0)),
            'minPromotion' => max(0, min(100, (int)$request->query->get('promotion', $request->query->get('minPromotion', 0)))),
        ];

        $cacheKey = $this->cacheKeyConventionService->buildShopProductListKey($page, $limit, array_merge($filters, ['scope' => 'global']));

        /** @var array<string,mixed> $result */
        $result = $this->cache->get($cacheKey, function (ItemInterface $item) use ($filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->shopProductListTag());
            }

            $esIds = $this->searchIdsFromElastic($filters);
            if ($esIds === []) {
                return [
                    'items' => [],
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'totalItems' => 0,
                        'totalPages' => 0,
                    ],
                ];
            }

            $qb = $this->productRepository->createQueryBuilder('product')
                ->leftJoin('product.shop', 'shop')
                ->leftJoin('product.category', 'category')
                ->leftJoin('product.tags', 'tag')
                ->addSelect('category', 'tag')
                ->andWhere('shop.isGlobal = true')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->orderBy('product.createdAt', 'DESC');

            if ($filters['name'] !== '') {
                $qb->andWhere('LOWER(product.name) LIKE LOWER(:name)')->setParameter('name', '%' . $filters['name'] . '%');
            }

            if ($filters['category'] !== '') {
                $qb->andWhere('LOWER(category.name) LIKE LOWER(:category)')->setParameter('category', '%' . $filters['category'] . '%');
            }

            if ($filters['status'] !== '') {
                $qb->andWhere('product.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['minPrice'] > 0) {
                $qb->andWhere('product.price >= :minPrice')->setParameter('minPrice', (int)round($filters['minPrice'] * 100));
            }
            if ($filters['maxPrice'] > 0) {
                $qb->andWhere('product.price <= :maxPrice')->setParameter('maxPrice', (int)round($filters['maxPrice'] * 100));
            }
            if ($filters['minPromotion'] > 0) {
                $qb->andWhere('product.promotionPercentage >= :minPromotion')->setParameter('minPromotion', $filters['minPromotion']);
            }

            if ($esIds !== null) {
                $qb->andWhere('product.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $items = array_map(static fn (Product $product): array => self::serializeProduct($product), $qb->getQuery()->getResult());

            $countQb = $this->productRepository->createQueryBuilder('product')
                ->select('COUNT(product.id)')
                ->leftJoin('product.shop', 'shop')
                ->leftJoin('product.category', 'category')
                ->andWhere('shop.isGlobal = true');

            if ($filters['name'] !== '') {
                $countQb->andWhere('LOWER(product.name) LIKE LOWER(:name)')->setParameter('name', '%' . $filters['name'] . '%');
            }
            if ($filters['category'] !== '') {
                $countQb->andWhere('LOWER(category.name) LIKE LOWER(:category)')->setParameter('category', '%' . $filters['category'] . '%');
            }
            if ($filters['status'] !== '') {
                $countQb->andWhere('product.status = :status')->setParameter('status', $filters['status']);
            }
            if ($filters['minPrice'] > 0) {
                $countQb->andWhere('product.price >= :minPrice')->setParameter('minPrice', (int)round($filters['minPrice'] * 100));
            }
            if ($filters['maxPrice'] > 0) {
                $countQb->andWhere('product.price <= :maxPrice')->setParameter('maxPrice', (int)round($filters['maxPrice'] * 100));
            }
            if ($filters['minPromotion'] > 0) {
                $countQb->andWhere('product.promotionPercentage >= :minPromotion')->setParameter('minPromotion', $filters['minPromotion']);
            }
            if ($esIds !== null) {
                $countQb->andWhere('product.id IN (:ids)')->setParameter('ids', $esIds);
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'module' => 'shop',
                    'scope' => 'global',
                ],
            ];
        });

        $result['meta']['filters'] = array_filter($filters, static fn (mixed $value): bool => $value !== '' && $value !== 0 && $value !== 0.0);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public static function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'sku' => $product->getSku(),
            'description' => $product->getDescription(),
            'texture' => $product->getTexture(),
            'photo' => $product->getPhoto(),
            'price' => MoneyFormatter::toApiAmount($product->getPrice()),
            'discountedPrice' => MoneyFormatter::toApiAmount(self::computeDiscountedPrice($product)),
            'currencyCode' => $product->getCurrencyCode(),
            'stock' => $product->getStock(),
            'coinsAmount' => $product->getCoinsAmount(),
            'promotionPercentage' => $product->getPromotionPercentage(),
            'isFeatured' => $product->isFeatured(),
            'status' => $product->getStatus()->value,
            'categoryId' => $product->getCategory()?->getId(),
            'categoryName' => $product->getCategory()?->getName(),
            'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $product->getTags()->toArray()),
            'seo' => [
                'title' => $product->getSeoTitle(),
                'description' => $product->getSeoDescription(),
                'keywords' => $product->getSeoKeywords(),
            ],
            'similarProductIds' => array_map(static fn (Product $similarProduct): string => $similarProduct->getId(), $product->getSimilarProducts()->toArray()),
            'updatedAt' => $product->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    private static function computeDiscountedPrice(Product $product): int
    {
        $discountRatio = max(0.0, min(1.0, $product->getPromotionPercentage() / 100));

        return (int)round($product->getPrice() * (1 - $discountRatio));
    }

    /** @param array<string, mixed> $filters
     * @return array<int, string>|null
     */
    private function searchIdsFromElastic(array $filters): ?array
    {
        if ($filters['q'] === '') {
            return null;
        }

        try {
            $response = $this->elasticsearchService->search(ShopProductProjection::INDEX_NAME, [
                'query' => [
                    'multi_match' => [
                        'query' => $filters['q'],
                        'type' => 'phrase_prefix',
                        'fields' => ['name^3', 'categoryName^2', 'tags', 'sku^4'],
                    ],
                ],
                '_source' => ['id'],
            ], 0, 200);
        } catch (Throwable) {
            return null;
        }

        $hits = $response['hits']['hits'] ?? [];

        return array_values(array_filter(array_map(static fn (array $hit): ?string => $hit['_source']['id'] ?? $hit['_id'] ?? null, $hits)));
    }
}
