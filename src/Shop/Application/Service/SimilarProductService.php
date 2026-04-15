<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;

readonly class SimilarProductService
{
    public function __construct(
        private ProductRepository $productRepository
    ) {
    }

    /**
     * @return array<int, Product>
     */
    public function getSimilarProducts(Product $product, int $limit = 8): array
    {
        $manual = $product->getSimilarProducts()->toArray();
        $candidates = $this->productRepository->findSimilarCandidates($product, $limit);

        $merged = [];
        foreach (array_merge($manual, $candidates) as $item) {
            if (!$item instanceof Product) {
                continue;
            }

            $merged[$item->getId()] = $item;
            if (count($merged) >= $limit) {
                break;
            }
        }

        return array_values($merged);
    }
}
