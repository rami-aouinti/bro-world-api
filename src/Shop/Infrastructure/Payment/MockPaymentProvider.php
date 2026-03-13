<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Payment;

use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;

use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_string;
use function json_encode;
use function sprintf;
use function strtolower;
use function trim;
use function uniqid;

final readonly class MockPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private string $appSecret,
    ) {
    }

    public function createIntent(string $orderId, int $amount, string $currency, array $metadata = []): array
    {
        $reference = sprintf('mock_intent_%s', uniqid('', true));

        return [
            'provider' => 'mock',
            'providerReference' => $reference,
            'status' => 'requires_confirmation',
            'payload' => [
                'orderId' => $orderId,
                'amount' => $amount,
                'currency' => $currency,
                'metadata' => $metadata,
            ],
        ];
    }

    public function confirm(string $providerReference, array $payload = []): array
    {
        $shouldFail = (bool)($payload['forceFail'] ?? false);

        return [
            'provider' => 'mock',
            'providerReference' => trim($providerReference),
            'status' => $shouldFail ? 'failed' : 'succeeded',
            'payload' => [
                'confirmed' => true,
                'forceFail' => $shouldFail,
                ...$payload,
            ],
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature = null): ?array
    {
        $providerReference = $payload['providerReference'] ?? null;
        $status = $payload['status'] ?? null;
        $eventId = $payload['eventId'] ?? null;

        if (!is_string($providerReference) || !is_string($status) || !is_string($eventId)) {
            return null;
        }

        if (is_string($signature) && trim($signature) !== '') {
            $encodedPayload = json_encode($payload);
            if (!is_string($encodedPayload)) {
                return null;
            }

            $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->appSecret);
            if (!hash_equals($expectedSignature, strtolower(trim($signature)))) {
                return null;
            }
        }

        return [
            'provider' => 'mock',
            'providerReference' => trim($providerReference),
            'status' => trim($status),
            'webhookKey' => trim($eventId),
            'payload' => is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload,
        ];
    }
}
