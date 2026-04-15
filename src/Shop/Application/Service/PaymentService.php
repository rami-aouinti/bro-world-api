<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Application\Monitoring\ShopMonitoringService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\OrderItem;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_string;
use function max;
use function trim;

final readonly class PaymentService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentTransactionRepository $paymentTransactionRepository,
        private PaymentProviderRouter $paymentProviderRouter,
        private Security $security,
        private string $environment,
        private ShopMonitoringService $monitoringService,
        private UserRepository $userRepository,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function createPaymentIntent(
        ?string $applicationSlug,
        string $orderId,
        ?string $provider = null,
        ?string $paymentMethod = null,
    ): PaymentTransaction {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Order not found.');
        }

        $this->assertOrderAccess($order, $applicationSlug);
        $this->assertOrderCoinsEligibility($order, $applicationSlug);

        if ($order->getStatus() !== OrderStatus::PENDING_PAYMENT) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order is not in pending_payment status.');
        }

        $providerKey = $this->paymentProviderRouter->resolveProviderKey($provider, $paymentMethod);
        $providerClient = $this->paymentProviderRouter->getProvider($providerKey);

        $providerIntent = $providerClient->createIntent(
            orderId: $order->getId(),
            amount: $order->getSubtotal(),
            currency: 'EUR',
            metadata: [
                'orderId' => $order->getId(),
            ],
        );

        $transaction = new PaymentTransaction()
            ->setOrder($order)
            ->setProvider((string)$providerIntent['provider'])
            ->setProviderReference((string)$providerIntent['providerReference'])
            ->setAmount($order->getSubtotal())
            ->setCurrency('EUR')
            ->setStatus($this->resolvePaymentStatus((string)$providerIntent['status']))
            ->setPayload((array)($providerIntent['payload'] ?? []));

        $this->assertPaymentConsistency($order, $transaction, $applicationSlug);

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function confirmPayment(?string $applicationSlug, string $orderId, string $providerReference, array $payload = []): PaymentTransaction
    {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Order not found.');
        }

        $this->assertOrderAccess($order, $applicationSlug);

        $transaction = $this->paymentTransactionRepository->findOneBy([
            'order' => $order,
            'providerReference' => $providerReference,
        ]);

        if (!$transaction instanceof PaymentTransaction) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Payment transaction not found.');
        }

        $providerResponse = $this->paymentProviderRouter
            ->getProvider($transaction->getProvider())
            ->confirm($providerReference, $payload);

        $transaction->setStatus($this->resolvePaymentStatus((string)$providerResponse['status']));
        $transaction->setPayload((array)($providerResponse['payload'] ?? []));

        $this->assertPaymentConsistency($order, $transaction, $applicationSlug);
        $this->applyOrderStateFromPayment($order, $transaction->getStatus());

        if ($transaction->getStatus() === PaymentStatus::FAILED) {
            $this->monitoringService->logStructured(
                event: 'shop.payment.confirm_failed',
                message: 'Payment confirmation failed.',
                context: [
                    'applicationSlug' => $applicationSlug,
                    'orderId' => $order->getId(),
                    'providerReference' => $providerReference,
                    'provider' => $transaction->getProvider(),
                ],
                level: 'error',
            );
            $this->monitoringService->incrementCounter('shop.payment_confirm.failures_total', [
                'reason' => 'provider_failed',
                'provider' => $transaction->getProvider(),
            ]);
        }

        $this->creditCoinsIfSucceeded($order, $transaction, $applicationSlug, 'confirm');

        $this->orderRepository->save($order, false);
        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function processWebhook(array $payload, ?string $signature = null, ?string $provider = null): ?PaymentTransaction
    {
        $normalizedSignature = is_string($signature) ? trim($signature) : '';
        if ($this->environment === 'prod' && $normalizedSignature === '') {
            $this->monitoringService->logStructured(
                event: 'shop.webhook.invalid',
                message: 'Webhook rejected because signature header is missing in production.',
                context: [
                    'environment' => $this->environment,
                    'reason' => 'missing_signature',
                ],
            );
            $this->monitoringService->incrementCounter('shop.webhook.invalid_total', [
                'reason' => 'missing_signature',
            ]);

            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Webhook signature is required in production.');
        }

        $providerKey = $this->paymentProviderRouter->resolveProviderKey(
            $provider,
            is_string($payload['provider'] ?? null) ? (string) $payload['provider'] : null,
            'stripe',
        );
        $providerClient = $this->paymentProviderRouter->getProvider($providerKey);

        $verifiedPayload = $providerClient->verifyWebhook($payload, $normalizedSignature !== '' ? $normalizedSignature : null);
        if ($verifiedPayload === null) {
            $this->monitoringService->logStructured(
                event: 'shop.webhook.invalid',
                message: 'Webhook rejected because signature or payload is invalid.',
                context: [
                    'reason' => 'invalid_signature_or_payload',
                    'provider' => $providerKey,
                ],
            );
            $this->monitoringService->incrementCounter('shop.webhook.invalid_total', [
                'reason' => 'invalid_signature_or_payload',
            ]);

            throw new HttpException(JsonResponse::HTTP_UNAUTHORIZED, 'Invalid webhook signature or payload.');
        }

        if (
            $this->paymentTransactionRepository->findOneBy([
                'webhookIdempotenceKey' => $verifiedPayload['webhookKey'],
            ]) instanceof PaymentTransaction
        ) {
            $this->monitoringService->logStructured(
                event: 'shop.webhook.replayed',
                message: 'Webhook replay detected and rejected.',
                context: [
                    'provider' => (string)$verifiedPayload['provider'],
                    'providerReference' => (string)$verifiedPayload['providerReference'],
                    'webhookKey' => (string)$verifiedPayload['webhookKey'],
                ],
            );
            $this->monitoringService->incrementCounter('shop.webhook.replayed_total', [
                'provider' => (string)$verifiedPayload['provider'],
            ]);

            return null;
        }

        $transaction = $this->paymentTransactionRepository->findOneBy([
            'provider' => $verifiedPayload['provider'],
            'providerReference' => $verifiedPayload['providerReference'],
        ]);

        if (!$transaction instanceof PaymentTransaction) {
            return null;
        }

        $transaction
            ->setStatus($this->resolvePaymentStatus((string)$verifiedPayload['status']))
            ->setWebhookIdempotenceKey((string)$verifiedPayload['webhookKey'])
            ->setPayload((array)($verifiedPayload['payload'] ?? []));

        $order = $transaction->getOrder();
        if ($order !== null) {
            $this->assertPaymentConsistency($order, $transaction, null);
            $this->applyOrderStateFromPayment($order, $transaction->getStatus());
            $this->creditCoinsIfSucceeded($order, $transaction, null, 'webhook');
            $this->orderRepository->save($order, false);
        }

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    private function assertOrderAccess(Order $order, ?string $applicationSlug): void
    {
        $requestedScope = $this->normalizeScope($applicationSlug);
        $orderScope = $this->resolveOrderScope($order);
        if ($orderScope !== $requestedScope) {
            $this->monitoringService->logStructured(
                event: 'shop.payment.scope_access_denied',
                message: 'Payment access rejected due to scope access refusal.',
                context: [
                    'applicationSlug' => $requestedScope,
                    'orderId' => $order->getId(),
                    'orderApplicationSlug' => $order->getShop()?->getApplication()?->getSlug(),
                    'orderShopIsGlobal' => $order->getShop()?->isGlobal(),
                    'shopId' => $order->getShop()?->getId(),
                ],
            );
            $this->monitoringService->incrementCounter('shop.payment_confirm.failures_total', [
                'reason' => 'scope_access_denied',
            ]);

            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'This order does not belong to the requested application scope.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $order->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'This order does not belong to the authenticated user.');
        }
    }

    private function normalizeScope(?string $applicationSlug): ?string
    {
        $scope = trim((string) $applicationSlug);

        return $scope === '' ? null : $scope;
    }

    private function resolveOrderScope(Order $order): ?string
    {
        $shop = $order->getShop();
        if ($shop === null) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order shop scope is invalid.');
        }

        if ($shop->isGlobal()) {
            if ($shop->getApplication() !== null) {
                throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Global shop configuration is invalid.');
            }

            return null;
        }

        $shopApplicationSlug = $shop->getApplication()?->getSlug();
        if (!is_string($shopApplicationSlug) || trim($shopApplicationSlug) === '') {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Application-scoped shop configuration is invalid.');
        }

        return trim($shopApplicationSlug);
    }

    private function applyOrderStateFromPayment(Order $order, PaymentStatus $status): void
    {
        if ($status === PaymentStatus::SUCCEEDED) {
            $order->setStatus(OrderStatus::PAID);

            return;
        }

        if ($status === PaymentStatus::FAILED) {
            $order->setStatus(OrderStatus::FAILED);
        }
    }

    private function resolvePaymentStatus(string $status): PaymentStatus
    {
        return match ($status) {
            PaymentStatus::REQUIRES_CONFIRMATION->value => PaymentStatus::REQUIRES_CONFIRMATION,
            PaymentStatus::SUCCEEDED->value => PaymentStatus::SUCCEEDED,
            PaymentStatus::FAILED->value => PaymentStatus::FAILED,
            default => PaymentStatus::CREATED,
        };
    }

    private function assertOrderCoinsEligibility(Order $order, ?string $applicationSlug): void
    {
        $computedSubtotal = 0;

        foreach ($order->getItems() as $item) {
            $computedSubtotal += $this->assertOrderItemConsistency($order, $item, $applicationSlug);
        }

        if ($computedSubtotal !== $order->getSubtotal()) {
            $this->trackPaymentValidationFailure('subtotal_mismatch', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order subtotal mismatch with order items.');
        }
    }

    private function assertOrderItemConsistency(Order $order, OrderItem $item, ?string $applicationSlug): int
    {
        if ($item->getOrder()?->getId() !== $order->getId()) {
            $this->trackPaymentValidationFailure('order_item_relation_mismatch', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order item relation mismatch detected.');
        }

        $product = $item->getProduct();
        if (!$product instanceof Product) {
            $this->trackPaymentValidationFailure('missing_product', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order item product is missing.');
        }

        if ($product->getCoinsAmount() <= 0) {
            $this->trackPaymentValidationFailure('non_coin_product', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order contains a product not eligible for coins credit.');
        }

        if ($product->getCurrencyCode() !== 'EUR') {
            $this->trackPaymentValidationFailure('unsupported_currency', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Only EUR products are eligible for checkout payment.');
        }

        $expectedLineTotal = $item->getUnitPriceSnapshot() * $item->getQuantity();
        if ($expectedLineTotal !== $item->getLineTotal()) {
            $this->trackPaymentValidationFailure('line_total_mismatch', $order, null, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order item line total mismatch detected.');
        }

        return $expectedLineTotal;
    }

    private function assertPaymentConsistency(Order $order, PaymentTransaction $transaction, ?string $applicationSlug): void
    {
        $this->assertOrderCoinsEligibility($order, $applicationSlug);

        if ($transaction->getCurrency() !== 'EUR') {
            $this->trackPaymentValidationFailure('transaction_currency_mismatch', $order, $transaction, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Payment currency must be EUR.');
        }

        if ($transaction->getAmount() !== $order->getSubtotal()) {
            $this->trackPaymentValidationFailure('transaction_amount_mismatch', $order, $transaction, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Payment amount does not match order subtotal.');
        }
    }

    private function creditCoinsIfSucceeded(Order $order, PaymentTransaction $transaction, ?string $applicationSlug, string $source): void
    {
        if ($transaction->getStatus() !== PaymentStatus::SUCCEEDED) {
            return;
        }

        $idempotenceReference = $this->resolveCoinsCreditReference($transaction);
        if ($idempotenceReference === '') {
            $this->trackPaymentValidationFailure('coins_credit_reference_missing', $order, $transaction, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Payment idempotence reference is missing for coins credit.');
        }

        if ($transaction->getCoinsCreditedAt() !== null || $transaction->getCoinsCreditReference() !== null) {
            $this->monitoringService->logStructured(
                event: 'shop.payment.coins_credit.skipped',
                message: 'Coins credit skipped because payment transaction was already credited.',
                context: [
                    'applicationSlug' => $applicationSlug,
                    'orderId' => $order->getId(),
                    'transactionId' => $transaction->getId(),
                    'providerReference' => $transaction->getProviderReference(),
                    'idempotenceReference' => $idempotenceReference,
                    'source' => $source,
                ],
                level: 'info',
            );
            $this->monitoringService->incrementCounter('shop.payment.coins_credit_total', [
                'result' => 'already_credited',
                'source' => $source,
            ]);

            return;
        }

        $coinsToCredit = 0;
        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product instanceof Product) {
                $coinsToCredit += $product->getCoinsAmount() * $item->getQuantity();
            }
        }

        if ($coinsToCredit <= 0) {
            $this->trackPaymentValidationFailure('coins_total_invalid', $order, $transaction, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order coins amount must be greater than zero.');
        }

        $user = $order->getUser();
        if (!$user instanceof User) {
            $this->trackPaymentValidationFailure('order_user_missing', $order, $transaction, $applicationSlug);
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order user is missing for coins credit.');
        }

        $user->setCoins(max(0, $user->getCoins() + $coinsToCredit));
        $this->userRepository->save($user, false);

        $transaction
            ->setCoinsCreditReference($idempotenceReference)
            ->setCoinsCreditedAt(new DateTimeImmutable());

        $this->monitoringService->logStructured(
            event: 'shop.payment.coins_credit.succeeded',
            message: 'Coins credited to user after payment success.',
            context: [
                'applicationSlug' => $applicationSlug,
                'orderId' => $order->getId(),
                'transactionId' => $transaction->getId(),
                'providerReference' => $transaction->getProviderReference(),
                'idempotenceReference' => $idempotenceReference,
                'coinsCredited' => $coinsToCredit,
                'source' => $source,
                'userId' => $user->getId(),
            ],
            level: 'info',
        );
        $this->monitoringService->incrementCounter('shop.payment.coins_credit_total', [
            'result' => 'credited',
            'source' => $source,
        ]);
    }

    private function resolveCoinsCreditReference(PaymentTransaction $transaction): string
    {
        $webhookKey = trim((string)($transaction->getWebhookIdempotenceKey() ?? ''));
        if ($webhookKey !== '') {
            return $webhookKey;
        }

        return trim($transaction->getProviderReference());
    }

    private function trackPaymentValidationFailure(
        string $reason,
        Order $order,
        ?PaymentTransaction $transaction,
        ?string $applicationSlug,
    ): void {
        $this->monitoringService->logStructured(
            event: 'shop.payment.validation_failed',
            message: 'Payment consistency validation failed.',
            context: [
                'reason' => $reason,
                'applicationSlug' => $applicationSlug,
                'orderId' => $order->getId(),
                'transactionId' => $transaction?->getId(),
                'providerReference' => $transaction?->getProviderReference(),
            ],
            level: 'error',
        );

        $this->monitoringService->incrementCounter('shop.payment.validation_failures_total', [
            'reason' => $reason,
        ]);
    }
}
