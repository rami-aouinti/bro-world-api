<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;

readonly class SimilarProductService
{
    public function __construct(private ProductRepository $productRepository)
    {
    }

    /**
     * @return array<int, Product>
     */
    public function getSimilarProducts(Product $product, int $limit = 8): array
    {
        return $this->productRepository->findSimilarCandidates($product, $limit);
    }
}
