<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shop\Application\Service;

use App\Platform\Domain\Entity\Application;
use App\Shop\Application\Service\PaymentService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class PaymentServiceTest extends TestCase
{
    public function testCreatePaymentIntentWithValidScope(): void
    {
        $owner = $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]);
        $order = $this->createOrder('app-shop', $owner, 4250);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->with($order->getId())->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentTransactionRepository->expects(self::once())->method('save');

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('createIntent')->willReturn([
            'provider' => 'mock',
            'providerReference' => 'mock-ref-1',
            'status' => 'requires_confirmation',
            'payload' => [
                'intent' => true,
            ],
        ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        $transaction = $service->createPaymentIntent('app-shop', $order->getId());

        self::assertSame('mock-ref-1', $transaction->getProviderReference());
        self::assertSame(PaymentStatus::REQUIRES_CONFIRMATION, $transaction->getStatus());
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
        self::assertSame(4250, $transaction->getAmount());
    }

    public function testCreatePaymentIntentWithInvalidScopeReturnsForbidden(): void
    {
        $owner = $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]);
        $order = $this->createOrder('app-shop', $owner, 1999);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentProvider = $this->createMock(PaymentProviderInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        try {
            $service->createPaymentIntent('wrong-scope', $order->getId());
            self::fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }

    public function testConfirmPaymentWithValidScopeMarksOrderPaid(): void
    {
        $owner = $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]);
        $order = $this->createOrder('app-shop', $owner, 9990);

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-2')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION)
            ->setAmount(9990)
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
            'payload' => [
                'confirmed' => true,
            ],
        ]);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        $confirmed = $service->confirmPayment('app-shop', $order->getId(), 'mock-ref-2');

        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::SUCCEEDED, $confirmed->getStatus());
    }

    public function testConfirmPaymentWithInvalidScopeReturnsForbidden(): void
    {
        $owner = $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]);
        $order = $this->createOrder('app-shop', $owner, 3000);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentProvider = $this->createMock(PaymentProviderInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($owner);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        try {
            $service->confirmPayment('wrong-scope', $order->getId(), 'mock-ref-2');
            self::fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }

    public function testCreatePaymentIntentWithDifferentAuthenticatedUserReturnsForbidden(): void
    {
        $owner = $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]);
        $otherUser = $this->createConfiguredMock(User::class, [
            'getId' => 'another-id',
        ]);
        $order = $this->createOrder('app-shop', $owner, 5000);

        $orderRepository = $this->createMock(OrderRepository::class);
        $orderRepository->method('find')->willReturn($order);

        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentProvider = $this->createMock(PaymentProviderInterface::class);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($otherUser);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        try {
            $service->createPaymentIntent('app-shop', $order->getId());
            self::fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_FORBIDDEN, $exception->getStatusCode());
        }
    }

    public function testProcessWebhookWithoutSignatureInProdReturnsBadRequest(): void
    {
        $orderRepository = $this->createMock(OrderRepository::class);
        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);
        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $security = $this->createMock(Security::class);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'prod');

        try {
            $service->processWebhook([
                'eventId' => 'evt-prod',
            ]);
            self::fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $exception->getStatusCode());
        }
    }

    public function testProcessWebhookWithInvalidPayloadReturnsUnauthorized(): void
    {
        $orderRepository = $this->createMock(OrderRepository::class);
        $paymentTransactionRepository = $this->createMock(PaymentTransactionRepository::class);

        $paymentProvider = $this->createMock(PaymentProviderInterface::class);
        $paymentProvider->method('verifyWebhook')->willReturn(null);

        $security = $this->createMock(Security::class);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        try {
            $service->processWebhook([
                'eventId' => 'evt-invalid',
            ], 'sig-invalid');
            self::fail('Expected HttpException to be thrown.');
        } catch (HttpException $exception) {
            self::assertSame(JsonResponse::HTTP_UNAUTHORIZED, $exception->getStatusCode());
        }
    }

    public function testProcessWebhookReturnsNullOnDuplicateWebhookKey(): void
    {
        $order = $this->createOrder('app-shop', $this->createConfiguredMock(User::class, [
            'getId' => 'owner-id',
        ]), 2500);
        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider('mock')
            ->setProviderReference('mock-ref-3')
            ->setStatus(PaymentStatus::REQUIRES_CONFIRMATION)
            ->setAmount(2500)
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
                    'status' => 'failed',
                    'webhookKey' => 'evt-1',
                    'payload' => [
                        'a' => 1,
                    ],
                ],
                [
                    'provider' => 'mock',
                    'providerReference' => 'mock-ref-3',
                    'status' => 'failed',
                    'webhookKey' => 'evt-duplicated',
                    'payload' => [
                        'a' => 1,
                    ],
                ],
            );

        $security = $this->createMock(Security::class);

        $service = new PaymentService($orderRepository, $paymentTransactionRepository, $paymentProvider, $security, 'test');

        $processed = $service->processWebhook([
            'eventId' => 'evt-1',
        ], 'sig-valid');
        $ignored = $service->processWebhook([
            'eventId' => 'evt-duplicated',
        ], 'sig-valid');

        self::assertInstanceOf(PaymentTransaction::class, $processed);
        self::assertNull($ignored);
        self::assertSame(OrderStatus::FAILED, $order->getStatus());
        self::assertSame('evt-1', $transaction->getWebhookIdempotenceKey());
    }

    private function createOrder(string $applicationSlug, User $owner, int $subtotal): Order
    {
        $application = (new Application())
            ->setTitle('Test App')
            ->setSlug($applicationSlug);

        $shop = (new Shop())
            ->setName('Test Shop')
            ->setApplication($application);

        return (new Order())
            ->setShop($shop)
            ->setUser($owner)
            ->setStatus(OrderStatus::PENDING_PAYMENT)
            ->setSubtotal($subtotal);
    }
}
