<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shop\Application\MessageHandler;

use App\Shop\Application\Message\CheckoutCommand;
use App\Shop\Application\Service\CheckoutService;
use App\Shop\Domain\Entity\Cart;
use App\Shop\Domain\Entity\CartItem;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\CartItemRepository;
use App\Shop\Infrastructure\Repository\CartRepository;
use App\Shop\Infrastructure\Repository\OrderItemRepository;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class CheckoutServiceTest extends TestCase
{
    public function testCheckoutSuccessCreatesOrderAndClearsCart(): void
    {
        $shop = (new Shop())->setName('Shop');
        $user = (new User())->setEmail('john@doe.test');
        $product = (new Product())
            ->setShop($shop)
            ->setName('Gaming Keyboard')
            ->setSku('kb-01')
            ->setPrice(49.9)
            ->setStock(10);

        $cartItem = (new CartItem())
            ->setProduct($product)
            ->setQuantity(2)
            ->setUnitPriceSnapshot($product->getPrice())
            ->setLineTotal(99.8);

        $cart = (new Cart())
            ->setShop($shop)
            ->setUser($user)
            ->setIsActive(true)
            ->setItemsCount(2)
            ->setSubtotal(99.8)
            ->addItem($cartItem);

        $service = $this->buildService($shop, $user, $cart, $product);

        $order = $service->checkout($this->command($shop, $user));

        self::assertSame(8, $product->getStock());
        self::assertFalse($cart->isActive());
        self::assertSame(0, $cart->getItemsCount());
        self::assertSame(0.0, $cart->getSubtotal());
        self::assertSame(99.8, $order->getSubtotal());
        self::assertCount(1, $order->getItems());
    }

    public function testCheckoutThrowsWhenStockIsInsufficient(): void
    {
        $shop = (new Shop())->setName('Shop');
        $user = new User();
        $product = (new Product())
            ->setShop($shop)
            ->setName('Mouse')
            ->setSku('mouse-01')
            ->setPrice(10)
            ->setStock(1);

        $cart = (new Cart())
            ->setShop($shop)
            ->setUser($user)
            ->setIsActive(true)
            ->addItem((new CartItem())->setProduct($product)->setQuantity(2));

        $service = $this->buildService($shop, $user, $cart, $product);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Insufficient stock');

        try {
            $service->checkout($this->command($shop, $user));
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_CONFLICT, $exception->getStatusCode());
            throw $exception;
        }
    }

    public function testCheckoutThrowsWhenCartIsEmpty(): void
    {
        $shop = (new Shop())->setName('Shop');
        $user = new User();
        $cart = (new Cart())
            ->setShop($shop)
            ->setUser($user)
            ->setIsActive(true);

        $service = $this->buildService($shop, $user, $cart, null);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Cart is empty.');

        $service->checkout($this->command($shop, $user));
    }

    private function buildService(Shop $shop, User $user, Cart $cart, ?Product $product): CheckoutService
    {
        $cartRepository = $this->createMock(CartRepository::class);
        $cartRepository->method('findActiveByUserAndShop')->willReturn($cart);
        $cartRepository->method('save');

        $cartItemRepository = $this->createMock(CartItemRepository::class);
        $cartItemRepository->method('remove');

        $productRepository = $this->createMock(ProductRepository::class);
        $productRepository->method('find')->willReturn($product);
        $productRepository->method('save');

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('save');

        $orderItemRepository = $this->createMock(OrderItemRepository::class);
        $orderItemRepository->method('save');

        $shopRepository = $this->createMock(ShopRepository::class);
        $shopRepository->method('find')->willReturn($shop);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($user);

        return new CheckoutService(
            cartRepository: $cartRepository,
            cartItemRepository: $cartItemRepository,
            productRepository: $productRepository,
            orderRepository: $orderRepository,
            orderItemRepository: $orderItemRepository,
            shopRepository: $shopRepository,
            userRepository: $userRepository,
        );
    }

    private function command(Shop $shop, User $user): CheckoutCommand
    {
        return new CheckoutCommand(
            operationId: 'op',
            shopId: $shop->getId(),
            userId: $user->getId(),
            billingAddress: '10 Main street',
            shippingAddress: '20 Main street',
            email: 'john@doe.test',
            phone: '123456',
            shippingMethod: 'express',
        );
    }
}
