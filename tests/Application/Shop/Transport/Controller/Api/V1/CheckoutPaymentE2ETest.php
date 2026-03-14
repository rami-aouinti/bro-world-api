<?php

declare(strict_types=1);

namespace App\Tests\Application\Shop\Transport\Controller\Api\V1;

use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Tests\TestCase\WebTestCase;
use App\User\Infrastructure\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

use function hash_hmac;
use function json_decode;
use function json_encode;
use function strtolower;

final class CheckoutPaymentE2ETest extends WebTestCase
{
    public function testAddToCartThenCheckoutCreatesPendingPaymentOrder(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        [$shopId, $productId] = $this->resolveShopAndProductIds('shop-ops-center');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/carts/' . $shopId . '/items',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'productId' => $productId,
                'quantity' => 2,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        $orderId = $this->checkoutAndExtractOrderId($client, $shopId);

        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        $order = $orderRepository->find($orderId);

        self::assertInstanceOf(Order::class, $order);
        self::assertSame(OrderStatus::PENDING_PAYMENT, $order->getStatus());
    }

    public function testPaymentIntentThenConfirmSucceededMarksOrderPaid(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        [$shopId, $orderId] = $this->createPendingPaymentOrderForAuthenticatedUser($client, 'shop-ops-center');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-intent',
            [],
            [],
            $this->getJsonHeaders(),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        /** @var array{id: string, providerReference: string, status: string} $intentPayload */
        $intentPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(PaymentStatus::REQUIRES_CONFIRMATION->value, $intentPayload['status']);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-confirm',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'providerReference' => $intentPayload['providerReference'],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);

        /** @var array{id: string, status: string} $confirmPayload */
        $confirmPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(PaymentStatus::SUCCEEDED->value, $confirmPayload['status']);

        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        /** @var PaymentTransactionRepository $paymentTransactionRepository */
        $paymentTransactionRepository = static::getContainer()->get(PaymentTransactionRepository::class);

        $order = $orderRepository->find($orderId);
        $transaction = $paymentTransactionRepository->find($confirmPayload['id']);

        self::assertInstanceOf(Order::class, $order);
        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::SUCCEEDED, $transaction->getStatus());
        self::assertSame($shopId, $order->getShop()?->getId());
    }

    public function testPaymentConfirmFailedMarksOrderFailed(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        [, $orderId] = $this->createPendingPaymentOrderForAuthenticatedUser($client, 'shop-ops-center');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-intent',
            [],
            [],
            $this->getJsonHeaders(),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        /** @var array{providerReference: string} $intentPayload */
        $intentPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-confirm',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'providerReference' => $intentPayload['providerReference'],
                'forceFail' => true,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);

        /** @var array{id: string, status: string} $confirmPayload */
        $confirmPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(PaymentStatus::FAILED->value, $confirmPayload['status']);

        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        /** @var PaymentTransactionRepository $paymentTransactionRepository */
        $paymentTransactionRepository = static::getContainer()->get(PaymentTransactionRepository::class);

        $order = $orderRepository->find($orderId);
        $transaction = $paymentTransactionRepository->find($confirmPayload['id']);

        self::assertInstanceOf(Order::class, $order);
        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame(OrderStatus::FAILED, $order->getStatus());
        self::assertSame(PaymentStatus::FAILED, $transaction->getStatus());
    }

    public function testWebhookValidSignatureUpdatesStatusesAndInvalidSignatureIsRejected(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        [, $orderId] = $this->createPendingPaymentOrderForAuthenticatedUser($client, 'shop-ops-center');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-intent',
            [],
            [],
            $this->getJsonHeaders(),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        /** @var array{id: string, providerReference: string} $intentPayload */
        $intentPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $webhookPayload = [
            'providerReference' => $intentPayload['providerReference'],
            'status' => PaymentStatus::SUCCEEDED->value,
            'eventId' => 'evt-e2e-valid-' . $intentPayload['id'],
            'payload' => ['source' => 'e2e-test'],
        ];

        $signature = $this->buildWebhookSignature($webhookPayload);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/payments/webhook',
            [],
            [],
            [
                ...$this->getJsonHeaders(),
                'HTTP_X-Signature' => $signature,
            ],
            json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);

        /** @var array{processed: bool, transactionId: string, status: string} $successPayload */
        $successPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($successPayload['processed']);
        self::assertSame(PaymentStatus::SUCCEEDED->value, $successPayload['status']);

        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        /** @var PaymentTransactionRepository $paymentTransactionRepository */
        $paymentTransactionRepository = static::getContainer()->get(PaymentTransactionRepository::class);

        $order = $orderRepository->find($orderId);
        $transaction = $paymentTransactionRepository->find($successPayload['transactionId']);

        self::assertInstanceOf(Order::class, $order);
        self::assertInstanceOf(PaymentTransaction::class, $transaction);
        self::assertSame(OrderStatus::PAID, $order->getStatus());
        self::assertSame(PaymentStatus::SUCCEEDED, $transaction->getStatus());

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/payments/webhook',
            [],
            [],
            [
                ...$this->getJsonHeaders(),
                'HTTP_X-Signature' => 'invalid-signature',
            ],
            json_encode([
                'providerReference' => $intentPayload['providerReference'],
                'status' => PaymentStatus::FAILED->value,
                'eventId' => 'evt-e2e-invalid-' . $intentPayload['id'],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_UNAUTHORIZED);
    }

    public function testWebhookDuplicateEventIsIdempotent(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        [, $orderId] = $this->createPendingPaymentOrderForAuthenticatedUser($client, 'shop-ops-center');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/orders/' . $orderId . '/payment-intent',
            [],
            [],
            $this->getJsonHeaders(),
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        /** @var array{id: string, providerReference: string} $intentPayload */
        $intentPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $webhookPayload = [
            'providerReference' => $intentPayload['providerReference'],
            'status' => PaymentStatus::FAILED->value,
            'eventId' => 'evt-e2e-duplicate-' . $intentPayload['id'],
            'payload' => ['source' => 'e2e-duplicate'],
        ];

        $signature = $this->buildWebhookSignature($webhookPayload);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/payments/webhook',
            [],
            [],
            [
                ...$this->getJsonHeaders(),
                'HTTP_X-Signature' => $signature,
            ],
            json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_OK);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/payments/webhook',
            [],
            [],
            [
                ...$this->getJsonHeaders(),
                'HTTP_X-Signature' => $signature,
            ],
            json_encode($webhookPayload, JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_ACCEPTED);

        /** @var array{processed: bool} $duplicatePayload */
        $duplicatePayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($duplicatePayload['processed']);

        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        /** @var PaymentTransactionRepository $paymentTransactionRepository */
        $paymentTransactionRepository = static::getContainer()->get(PaymentTransactionRepository::class);

        $order = $orderRepository->find($orderId);
        $transactions = $paymentTransactionRepository->findBy([
            'order' => $order,
        ]);

        self::assertInstanceOf(Order::class, $order);
        self::assertCount(1, $transactions);
        self::assertSame(OrderStatus::FAILED, $order->getStatus());
        self::assertSame(PaymentStatus::FAILED, $transactions[0]->getStatus());
        self::assertSame($webhookPayload['eventId'], $transactions[0]->getWebhookIdempotenceKey());
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveShopAndProductIds(string $applicationSlug): array
    {
        /** @var ShopRepository $shopRepository */
        $shopRepository = static::getContainer()->get(ShopRepository::class);
        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);

        $shop = $shopRepository->findOneByApplicationSlug($applicationSlug);
        self::assertNotNull($shop);

        $product = $productRepository->findBy(['shop' => $shop], ['createdAt' => 'ASC'])[0] ?? null;
        self::assertNotNull($product);

        return [$shop->getId(), $product->getId()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createPendingPaymentOrderForAuthenticatedUser($client, string $applicationSlug): array
    {
        [$shopId, $productId] = $this->resolveShopAndProductIds($applicationSlug);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/' . $applicationSlug . '/carts/' . $shopId . '/items',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'productId' => $productId,
                'quantity' => 1,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(JsonResponse::HTTP_CREATED);

        return [$shopId, $this->checkoutAndExtractOrderId($client, $shopId)];
    }

    private function checkoutAndExtractOrderId($client, string $shopId): string
    {
        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/checkout/' . $shopId,
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'billingAddress' => '10 Main street',
                'shippingAddress' => '20 Main street',
                'email' => 'john@doe.test',
                'phone' => '123456',
                'shippingMethod' => 'express',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertContains($client->getResponse()->getStatusCode(), [JsonResponse::HTTP_CREATED, JsonResponse::HTTP_ACCEPTED]);

        /** @var array{id?: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        if (($payload['id'] ?? '') !== '') {
            return (string)$payload['id'];
        }

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var OrderRepository $orderRepository */
        $orderRepository = static::getContainer()->get(OrderRepository::class);
        $user = $userRepository->findOneBy([
            'username' => 'john-root',
        ]);

        self::assertNotNull($user);

        $order = $orderRepository->findBy([
            'shop' => $shopId,
            'user' => $user,
        ], ['createdAt' => 'DESC'])[0] ?? null;

        self::assertInstanceOf(Order::class, $order);

        return $order->getId();
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildWebhookSignature(array $payload): string
    {
        $encodedPayload = json_encode($payload, JSON_THROW_ON_ERROR);

        /** @var string $appSecret */
        $appSecret = static::getContainer()->getParameter('kernel.secret');

        return strtolower(hash_hmac('sha256', $encodedPayload, $appSecret));
    }
}
