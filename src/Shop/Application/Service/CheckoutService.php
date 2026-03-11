<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Domain\Entity\Cart;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\OrderItem;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Infrastructure\Repository\CartItemRepository;
use App\Shop\Infrastructure\Repository\CartRepository;
use App\Shop\Infrastructure\Repository\OrderItemRepository;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\DBAL\LockMode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class CheckoutService
{
    public function __construct(
        private CartRepository $cartRepository,
        private CartItemRepository $cartItemRepository,
        private ProductRepository $productRepository,
        private OrderRepository $orderRepository,
        private OrderItemRepository $orderItemRepository,
        private ShopRepository $shopRepository,
        private UserRepository $userRepository,
    ) {
    }

    public function checkout(CheckoutCommand $command): Order
    {
        $shop = $this->shopRepository->find($command->shopId);
        if ($shop === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Shop not found.');
        }

        $user = $this->userRepository->find($command->userId);
        if ($user === null) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Authenticated user required.');
        }

        $cart = $this->cartRepository->findActiveByUserAndShop($user->getId(), $shop->getId());
        if (!$cart instanceof Cart) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Active cart not found.');
        }

        if ($cart->getItems()->count() === 0) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Cart is empty.');
        }

        $order = (new Order())
            ->setShop($shop)
            ->setUser($user)
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setBillingAddress($command->billingAddress)
            ->setShippingAddress($command->shippingAddress)
            ->setEmail($command->email)
            ->setPhone($command->phone)
            ->setShippingMethod($command->shippingMethod);

        $subtotal = 0.0;

        foreach ($cart->getItems() as $cartItem) {
            $product = $this->productRepository->find($cartItem->getProduct()?->getId() ?? '', LockMode::PESSIMISTIC_WRITE);
            if (!$product instanceof Product || $product->getShop()?->getId() !== $shop->getId()) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Product not found for this shop.');
            }

            if ($product->getStock() < $cartItem->getQuantity()) {
                throw new HttpException(JsonResponse::HTTP_CONFLICT, sprintf('Insufficient stock for SKU %s.', $product->getSku()));
            }

            $lineTotal = $product->getPrice() * $cartItem->getQuantity();
            $orderItem = (new OrderItem())
                ->setOrder($order)
                ->setProduct($product)
                ->setQuantity($cartItem->getQuantity())
                ->setUnitPriceSnapshot($product->getPrice())
                ->setLineTotal($lineTotal)
                ->setProductNameSnapshot($product->getName())
                ->setProductSkuSnapshot($product->getSku());

            $order->addItem($orderItem);
            $this->orderItemRepository->save($orderItem, false);

            $product->setStock($product->getStock() - $cartItem->getQuantity());
            $this->productRepository->save($product, false);

            $subtotal += $lineTotal;
        }

        $order->setSubtotal($subtotal);
        $this->orderRepository->save($order, false);

        foreach ($cart->getItems()->toArray() as $item) {
            $cart->removeItem($item);
            $this->cartItemRepository->remove($item, false);
        }

        $cart
            ->setSubtotal(0)
            ->setItemsCount(0)
            ->setIsActive(false);
        $this->cartRepository->save($cart, false);

        return $order;
    }
}

