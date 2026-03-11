<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shop\Application\Service;

use App\Shop\Application\Service\PaymentService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use PHPUnit\Framework\TestCase;

final class PaymentServiceTest extends TestCase
{
    public function testCreatePaymentIntentForPendingOrder(): void
    {
        $order = (new Order())
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setSubtotal(42.5);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository->expects(self::once())->method('save');

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('createIntent')->willReturn([
            'provider' => 'mock',
            'providerReference' => 'mock-ref-1',
            'status' => 'requires_confirmation',
            'payload' => ['intent' => true],
        ]);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider);

        $transaction = $service->createPaymentIntent($order->getId());

        self::assertSame('mock-ref-1', $transaction->getProviderReference());
        self::assertSame(PaymentStatus::REQUIRES_CONFIRMATION, $transaction->getStatus());
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
    }

    public function testConfirmPaymentMarksOrderPaid(): void
    {
        $order = (new Order())
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setSubtotal(99.9);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-2')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION);

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

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider);
        $service->confirmPayment($order->getId(), 'mock-ref-2');

        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::SUCCEEDED, $transaction->getStatus());
    }

    public function testProcessWebhookIsIdempotentWithWebhookKey(): void
    {
        $order = (new Order())
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setSubtotal(25.0);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-3')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->expects(self::once())->method('save');

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($transaction): ?PaymentTransaction {
                if (isset($criteria['webhookIdempotenceKey']) && $criteria['webhookIdempotenceKey'] === 'evt-duplicated') {
                    return new PaymentTransaction();
                }

                if (isset($criteria['providerReference']) && $criteria['providerReference'] === 'mock-ref-3') {
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
                    'status' => 'failed',
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

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider);

        $processed = $service->processWebhook(['eventId' => 'evt-1']);
        $ignored = $service->processWebhook(['eventId' => 'evt-duplicated']);

        self::assertInstanceOf(PaymentTransaction::class, $processed);
        self::assertNull($ignored);
        self::assertSame(OrderStatus::FAILED, $order->getStatus());
        self::assertSame('evt-1', $transaction->getWebhookIdempotenceKey());
    }
}
