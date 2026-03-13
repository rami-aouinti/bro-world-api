<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Shop\Application\Projection\ShopProductProjection;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
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

        $result['meta']['filters'] = array_filter($filters, static fn (string $value): bool => $value !== '');

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
            'price' => MoneyFormatter::toApiAmount($product->getPrice()),
            'currencyCode' => $product->getCurrencyCode(),
            'stock' => $product->getStock(),
            'isFeatured' => $product->isFeatured(),
            'status' => $product->getStatus()->value,
            'categoryId' => $product->getCategory()?->getId(),
            'categoryName' => $product->getCategory()?->getName(),
            'tags' => array_map(static fn ($tag): string => $tag->getLabel(), $product->getTags()->toArray()),
            'updatedAt' => $product->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /** @param array<string, string> $filters
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
