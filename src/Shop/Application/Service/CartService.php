<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Entity\Cart;
use App\Shop\Domain\Entity\CartItem;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\CartItemRepository;
use App\Shop\Infrastructure\Repository\CartRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;

readonly class CartService
{
    public function __construct(
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function getOrCreateActiveCart(User $user, Shop $shop): Cart
    {
        $cart = $this->cartRepository->findActiveByUserAndShop($user->getId(), $shop->getId());
        if ($cart instanceof Cart) {
            return $cart;
        }

        $cart = new Cart()
            ->setUser($user)
            ->setShop($shop)
            ->setIsActive(true)
            ->setItemsCount(0)
            ->setSubtotal(0);

        $this->cartRepository->save($cart, false);
        $this->cartRepository->getEntityManager()->flush();

        return $cart;
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function addProduct(Cart $cart, Product $product, int $quantity): Cart
    {
        $quantity = max(1, $quantity);

        foreach ($cart->getItems() as $existingItem) {
            if ($existingItem->getProduct()?->getId() !== $product->getId()) {
                continue;
            }

            $existingItem->setQuantity($existingItem->getQuantity() + $quantity);
            $existingItem->setUnitPriceSnapshot($product->getPrice());
            $existingItem->setLineTotal($existingItem->getQuantity() * $existingItem->getUnitPriceSnapshot());
            $this->cartItemRepository->save($existingItem, false);

            return $this->recalculate($cart);
        }

        $item = new CartItem()
            ->setCart($cart)
            ->setProduct($product)
            ->setQuantity($quantity)
            ->setUnitPriceSnapshot($product->getPrice())
            ->setLineTotal($product->getPrice() * $quantity);

        $cart->addItem($item);
        $this->cartItemRepository->save($item, false);

        return $this->recalculate($cart);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeCart(Cart $cart): array
    {
        return [
            'id' => $cart->getId(),
            'shopId' => $cart->getShop()?->getId(),
            'userId' => $cart->getUser()?->getId(),
            'subtotal' => MoneyFormatter::toApiAmount($cart->getSubtotal()),
            'itemsCount' => $cart->getItemsCount(),
            'currencyCode' => $cart->getItems()->first() instanceof CartItem
                ? $cart->getItems()->first()->getProduct()?->getCurrencyCode()
                : null,
            'updatedAt' => $cart->getUpdatedAt()?->format(DATE_ATOM),
            'items' => array_map(function (CartItem $item): array {
                $product = $item->getProduct();
                $serializedProduct = $product instanceof Product ? ProductListService::serializeProduct($product) : [];

                return [
                    'id' => $item->getId(),
                    'productId' => $product?->getId(),
                    'quantity' => $item->getQuantity(),
                    'unitPriceSnapshot' => MoneyFormatter::toApiAmount($item->getUnitPriceSnapshot()),
                    'lineTotal' => MoneyFormatter::toApiAmount($item->getLineTotal()),
                    'updatedAt' => $item->getUpdatedAt()?->format(DATE_ATOM),
                    'product' => [
                        'id' => $serializedProduct['id'] ?? null,
                        'name' => $serializedProduct['name'] ?? null,
                        'sku' => $serializedProduct['sku'] ?? null,
                        'price' => $serializedProduct['price'] ?? null,
                        'currencyCode' => $serializedProduct['currencyCode'] ?? null,
                        'stock' => $serializedProduct['stock'] ?? null,
                        'status' => $serializedProduct['status'] ?? null,
                    ],
                ];
            }, $cart->getItems()->toArray()),
        ];
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function updateItemQuantity(Cart $cart, CartItem $item, int $quantity): Cart
    {
        if ($item->getCart()?->getId() !== $cart->getId()) {
            return $cart;
        }

        $quantity = max(1, $quantity);
        $item->setQuantity($quantity);
        $item->setLineTotal($item->getUnitPriceSnapshot() * $quantity);

        $this->cartItemRepository->save($item, false);

        return $this->recalculate($cart);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function removeItem(Cart $cart, CartItem $item): Cart
    {
        if ($item->getCart()?->getId() !== $cart->getId()) {
            return $cart;
        }

        $cart->removeItem($item);
        $this->cartItemRepository->remove($item, false);

        return $this->recalculate($cart);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function recalculate(Cart $cart): Cart
    {
        $subtotal = 0;
        $itemsCount = 0;

        foreach ($cart->getItems() as $item) {
            $lineTotal = $item->getQuantity() * $item->getUnitPriceSnapshot();
            $item->setLineTotal($lineTotal);
            $subtotal += $lineTotal;
            $itemsCount += $item->getQuantity();
        }

        $cart->setSubtotal($subtotal);
        $cart->setItemsCount($itemsCount);

        $this->cartRepository->save($cart, false);
        $this->cartRepository->getEntityManager()->flush();

        return $cart;
    }
}
