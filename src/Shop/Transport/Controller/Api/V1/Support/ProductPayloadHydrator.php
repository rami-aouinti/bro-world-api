<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Support;

use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Domain\Enum\ProductStatus;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use App\Shop\Infrastructure\Repository\TagRepository;

final readonly class ProductPayloadHydrator
{
    public function __construct(
        private CategoryRepository $categoryRepository,
        private TagRepository $tagRepository,
    ) {
    }

    /** @param array<string,mixed> $payload */
    public function hydrate(Product $product, array $payload, bool $partial = false): Product
    {
        if (!$partial || array_key_exists('name', $payload)) {
            $product->setName((string) ($payload['name'] ?? ''));
        }
        if (!$partial || array_key_exists('price', $payload)) {
            $product->setPrice((float) ($payload['price'] ?? 0));
        }
        if (!$partial || array_key_exists('sku', $payload)) {
            $defaultSku = strtoupper(substr(md5($product->getId()), 0, 12));
            $product->setSku((string) ($payload['sku'] ?? $defaultSku));
        }
        if (!$partial || array_key_exists('description', $payload)) {
            $product->setDescription(($payload['description'] ?? null) !== null ? (string) $payload['description'] : null);
        }
        if (!$partial || array_key_exists('currencyCode', $payload)) {
            $product->setCurrencyCode((string) ($payload['currencyCode'] ?? 'EUR'));
        }
        if (!$partial || array_key_exists('stock', $payload)) {
            $product->setStock((int) ($payload['stock'] ?? 0));
        }
        if (!$partial || array_key_exists('isFeatured', $payload)) {
            $product->setIsFeatured((bool) ($payload['isFeatured'] ?? false));
        }
        if (!$partial || array_key_exists('status', $payload)) {
            $status = ProductStatus::tryFrom((string) ($payload['status'] ?? '')) ?? ProductStatus::DRAFT;
            $product->setStatus($status);
        }

        if (!$partial || array_key_exists('categoryId', $payload)) {
            $category = null;
            if (is_string($payload['categoryId'] ?? null)) {
                $category = $this->categoryRepository->find($payload['categoryId']);
                if ($category instanceof Category && $product->getShop() instanceof Shop && $category->getShop()?->getId() !== $product->getShop()?->getId()) {
                    $category = null;
                }
            }
            $product->setCategory($category instanceof Category ? $category : null);
        }

        if (!$partial || array_key_exists('tagIds', $payload)) {
            foreach ($product->getTags()->toArray() as $tag) {
                $product->removeTag($tag);
            }
            foreach ((array) ($payload['tagIds'] ?? []) as $tagId) {
                if (is_string($tagId) && ($tag = $this->tagRepository->find($tagId)) instanceof Tag) {
                    $product->addTag($tag);
                }
            }
        }

        return $product;
    }
}
