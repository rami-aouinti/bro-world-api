<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Application\Monitoring\ShopMonitoringService;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_string;
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
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    public function createPaymentIntent(
        string $applicationSlug,
        string $orderId,
        ?string $provider = null,
        ?string $paymentMethod = null,
    ): PaymentTransaction {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Order not found.');
        }

        $this->assertOrderAccess($order, $applicationSlug);

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

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    /**
     * @param array<string, mixed> $payload
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function confirmPayment(string $applicationSlug, string $orderId, string $providerReference, array $payload = []): PaymentTransaction
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
            $this->applyOrderStateFromPayment($order, $transaction->getStatus());
            $this->orderRepository->save($order, false);
        }

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    private function assertOrderAccess(Order $order, string $applicationSlug): void
    {
        $orderApplicationSlug = $order->getShop()?->getApplication()?->getSlug();
        if ($orderApplicationSlug !== trim($applicationSlug)) {
            $this->monitoringService->logStructured(
                event: 'shop.payment.scope_access_denied',
                message: 'Payment access rejected due to scope access refusal.',
                context: [
                    'applicationSlug' => trim($applicationSlug),
                    'orderId' => $order->getId(),
                    'orderApplicationSlug' => $orderApplicationSlug,
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
}
