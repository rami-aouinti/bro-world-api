<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Entity\PaymentTransaction;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Enum\OrderStatus;
use App\Shop\Domain\Enum\PaymentStatus;
use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\Shop\Infrastructure\Repository\PaymentTransactionRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function in_array;

final readonly class PaymentService
{
    public function __construct(
        private OrderRepository $orderRepository,
        private PaymentTransactionRepository $paymentTransactionRepository,
        private PaymentProviderInterface $paymentProvider,
    ) {
    }

    public function createPaymentIntent(string $orderId): PaymentTransaction
    {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Order not found.');
        }

        if ($order->getStatus() !== OrderStatus::PENDING_PAYMENT) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Order is not in pending_payment status.');
        }

        $providerIntent = $this->paymentProvider->createIntent(
            orderId: $order->getId(),
            amount: $order->getSubtotal(),
            currency: 'EUR',
            metadata: ['orderId' => $order->getId()],
        );

        $transaction = (new PaymentTransaction())
            ->setOrder($order)
            ->setProvider((string) $providerIntent['provider'])
            ->setProviderReference((string) $providerIntent['providerReference'])
            ->setAmount($order->getSubtotal())
            ->setCurrency('EUR')
            ->setStatus($this->resolvePaymentStatus((string) $providerIntent['status']))
            ->setPayload((array) ($providerIntent['payload'] ?? []));

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    /** @param array<string, mixed> $payload */
    public function confirmPayment(string $orderId, string $providerReference, array $payload = []): PaymentTransaction
    {
        $order = $this->orderRepository->find($orderId);
        if ($order === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Order not found.');
        }

        $transaction = $this->paymentTransactionRepository->findOneBy([
            'order' => $order,
            'providerReference' => $providerReference,
        ]);

        if (!$transaction instanceof PaymentTransaction) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Payment transaction not found.');
        }

        $providerResponse = $this->paymentProvider->confirm($providerReference, $payload);
        $transaction->setStatus($this->resolvePaymentStatus((string) $providerResponse['status']));
        $transaction->setPayload((array) ($providerResponse['payload'] ?? []));

        $this->applyOrderStateFromPayment($order, $transaction->getStatus());

        $this->orderRepository->save($order, false);
        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    /** @param array<string, mixed> $payload */
    public function processWebhook(array $payload, ?string $signature = null): ?PaymentTransaction
    {
        $verifiedPayload = $this->paymentProvider->verifyWebhook($payload, $signature);
        if ($verifiedPayload === null) {
            return null;
        }

        if ($this->paymentTransactionRepository->findOneBy([
            'webhookIdempotenceKey' => $verifiedPayload['webhookKey'],
        ]) instanceof PaymentTransaction) {
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
            ->setStatus($this->resolvePaymentStatus((string) $verifiedPayload['status']))
            ->setWebhookIdempotenceKey((string) $verifiedPayload['webhookKey'])
            ->setPayload((array) ($verifiedPayload['payload'] ?? []));

        $order = $transaction->getOrder();
        if ($order !== null) {
            $this->applyOrderStateFromPayment($order, $transaction->getStatus());
            $this->orderRepository->save($order, false);
        }

        $this->paymentTransactionRepository->save($transaction, true);

        return $transaction;
    }

    private function applyOrderStateFromPayment(Order $order, PaymentStatus $status): void
    {
        if ($status === PaymentStatus::SUCCEEDED) {
            $order->setStatus(OrderStatus::PAID);

            return;
        }

        if (in_array($status, [PaymentStatus::FAILED], true)) {
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
