<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shop\Application\Service;

use App\Platform\Domain\Entity\Application;
use App\Shop\Application\Monitoring\ShopMonitoringService;
use App\Shop\Application\Service\PaymentProviderRouter;
use App\Shop\Application\Service\PaymentService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\OrderItem;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentServiceTest extends TestCase
{
    public function testConfirmPaymentSucceededCreditsCoinsOnce(): void
    {
        $owner = $this->createConfiguredMock(User::class, ['getId' => 'owner-id', 'getCoins' => 100]);
        $owner->expects(self::once())->method('setCoins')->with(500)->willReturnSelf();

        $order = $this->createOrder('app-shop', $owner, 1200, 2, 200);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-2')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION)
            ->setAmount(1200)
            ->setCurrency('EUR');

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);
        $orderRepository->expects(self::once())->method('save');

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository->method('findOneBy')->willReturn($transaction);
        $paymentTransactionRepository->expects(self::once())->method('save');

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('confirm')->willReturn([
            'provider' => 'mock',
            'providerReference' => 'mock-ref-2',
            'status' => 'succeeded',
            'payload' => ['confirmed' => true],
        ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $monitoring = $this->createMock(ShopMonitoringService::class);
        $monitoring->expects(self::atLeastOnce())->method('incrementCounter');

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('save')->with($owner, false);

        $service = $this->createService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, $monitoring, $userRepository);

        $confirmed = $service->confirmPayment('app-shop', $order->getId(), 'mock-ref-2');

        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::SUCCEEDED, $confirmed->getStatus());
        self::assertSame('mock-ref-2', $confirmed->getCoinsCreditReference());
        self::assertNotNull($confirmed->getCoinsCreditedAt());
    }

    public function testConfirmPaymentSucceededDoesNotCreditTwice(): void
    {
        $owner = $this->createConfiguredMock(User::class, ['getId' => 'owner-id', 'getCoins' => 100]);
        $owner->expects(self::never())->method('setCoins');

        $order = $this->createOrder('app-shop', $owner, 1200, 1, 200);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-already')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION)
            ->setAmount(1200)
            ->setCurrency('EUR')
            ->setCoinsCreditReference('existing-key')
            ->setCoinsCreditedAt(new \DateTimeImmutable());

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository->method('findOneBy')->willReturn($transaction);

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('confirm')->willReturn([
            'provider' => 'mock',
            'providerReference' => 'mock-ref-already',
            'status' => 'succeeded',
            'payload' => ['confirmed' => true],
        ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $monitoring = $this->createMock(ShopMonitoringService::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::never())->method('save');

        $service = $this->createService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, $monitoring, $userRepository);
        $service->confirmPayment('app-shop', $order->getId(), 'mock-ref-already');
    }

    public function testCreatePaymentIntentRejectsNonCoinProduct(): void
    {
        $owner = $this->createConfiguredMock(User::class, ['getId' => 'owner-id']);
        $order = $this->createOrder('app-shop', $owner, 500, 1, 0);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentProvider = $this->createMock(PaymentProviderInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $monitoring = $this->createMock(ShopMonitoringService::class);
        $monitoring->expects(self::once())->method('incrementCounter')->with('shop.payment.validation_failures_total', ['reason' => 'non_coin_product']);

        $userRepository = $this->createMock(UserRepository::class);

        $service = $this->createService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, $monitoring, $userRepository);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Order contains a product not eligible for coins credit.');
        $service->createPaymentIntent('app-shop', $order->getId());
    }

    public function testProcessWebhookReturnsNullOnDuplicateWebhookKey(): void
    {
        $owner = $this->createConfiguredMock(User::class, ['getId' => 'owner-id', 'getCoins' => 100]);
        $owner->expects(self::once())->method('setCoins')->with(300)->willReturnSelf();
        $order = $this->createOrder('app-shop', $owner, 1200, 1, 200);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-3')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION)
            ->setAmount(1200)
            ->setCurrency('EUR');

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects(self::once())->method('save');

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($transaction): ?PaymentTransaction {
                if (($criteria['webhookIdempotenceKey'] ?? null) === 'evt-duplicated') {
                    return new PaymentTransaction();
                }

                if (($criteria['providerReference'] ?? null) === 'mock-ref-3') {
                    return $transaction;
                }

                return null;
            });
        $paymentTransactionRepository->expects(self::once())->method('save');

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('verifyWebhook')
            ->willReturnOnConsecutiveCalls(
                [
                    'provider' => 'mock',
                    'providerReference' => 'mock-ref-3',
                    'status' => 'succeeded',
                    'webhookKey' => 'evt-1',
                    'payload' => ['a' => 1],
                ],
                [
                    'provider' => 'mock',
                    'providerReference' => 'mock-ref-3',
                    'status' => 'failed',
                    'webhookKey' => 'evt-duplicated',
                    'payload' => ['a' => 1],
                ],
            );

        $security = $this->createMock(Security::class);
        $monitoring = $this->createMock(ShopMonitoringService::class);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects(self::once())->method('save')->with($owner, false);

        $service = $this->createService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, $monitoring, $userRepository);

        $processed = $service->processWebhook(['eventId' => 'evt-1'], 'sig-valid');
        $ignored = $service->processWebhook(['eventId' => 'evt-duplicated'], 'sig-valid');

        self::assertInstanceOf(PaymentTransaction::class, $processed);
        self::assertNull($ignored);
        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame('evt-1', $transaction->getWebhookIdempotenceKey());
    }

    private function createService(
        OrderRepository $orderRepository,
        PaymentTransactionRepository $paymentTransactionRepository,
        PaymentProviderInterface $provider,
        Security $security,
        ShopMonitoringService $monitoringService,
        UserRepository $userRepository,
    ): PaymentService {
        return new PaymentService(
            $orderRepository,
            $paymentTransactionRepository,
            new PaymentProviderRouter(['mock' => $provider, 'stripe' => $provider]),
            $security,
            'test',
            $monitoringService,
            $userRepository,
        );
    }

    private function createOrder(string $applicationSlug, User $owner, int $subtotal, int $quantity, int $coinsAmount): Order
    {
        $application = (new Application())
            ->setTitle('Test App')
            ->setSlug($applicationSlug);

        $shop = (new Shop())
            ->setName('Test Shop')
            ->setApplication($application);

        $product = (new Product())
            ->setName('Coins pack')
            ->setSku('COINS-PACK-1')
            ->setPrice((int)($subtotal / $quantity))
            ->setCurrencyCode('EUR')
            ->setCoinsAmount($coinsAmount);

        $item = (new OrderItem())
            ->setProduct($product)
            ->setQuantity($quantity)
            ->setUnitPriceSnapshot((int)($subtotal / $quantity))
            ->setLineTotal($subtotal)
            ->setProductNameSnapshot('Coins pack')
            ->setProductSkuSnapshot('COINS-PACK-1');

        $order = (new Order())
            ->setShop($shop)
            ->setUser($owner)
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setSubtotal($subtotal);

        $order->addItem($item);

        return $order;
    }
}
