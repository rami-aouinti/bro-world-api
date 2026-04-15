<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Shop;

final class ShopApiSerializer
{
    /**
     * @return array<string,mixed>
     */
    public static function serializeShop(Shop $shop): array
    {
        return [
            'id' => $shop->getId(),
            'name' => $shop->getName(),
            'description' => $shop->getDescription(),
            'isActive' => $shop->isActive(),
            'isGlobal' => $shop->isGlobal(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public static function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'slug' => $category->getSlug(),
            'description' => $category->getDescription(),
            'photo' => $category->getPhoto(),
            'shopId' => $category->getShop()?->getId(),
        ];
    }
}
