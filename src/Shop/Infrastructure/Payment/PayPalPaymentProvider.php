<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\Payment;

use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function base64_encode;
use function hash;
use function hash_equals;
use function hash_hmac;
use function is_array;
use function is_string;
use function json_encode;
use function number_format;
use function sprintf;
use function strtolower;
use function trim;

final readonly class PayPalPaymentProvider implements PaymentProviderInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $clientId,
        private string $clientSecret,
        private string $webhookSecret,
        private string $apiBaseUrl,
    ) {
    }

    public function createIntent(string $orderId, int $amount, string $currency, array $metadata = []): array
    {
        $accessToken = $this->fetchAccessToken();

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl('/v2/checkout/orders'), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [[
                        'reference_id' => $orderId,
                        'amount' => [
                            'currency_code' => strtoupper(trim($currency)),
                            'value' => number_format($amount / 100, 2, '.', ''),
                        ],
                        'custom_id' => $metadata['orderId'] ?? $orderId,
                    ]],
                    'application_context' => [
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                    ],
                ],
            ]);
            $payload = $response->toArray(false);
        } catch (ExceptionInterface) {
            return [
                'provider' => 'paypal',
                'providerReference' => '',
                'status' => 'failed',
                'payload' => [
                    'error' => 'paypal_create_intent_failed',
                ],
            ];
        }

        $providerReference = is_string($payload['id'] ?? null) ? trim((string) $payload['id']) : '';

        return [
            'provider' => 'paypal',
            'providerReference' => $providerReference,
            'status' => 'requires_confirmation',
            'payload' => [
                'paypalOrderId' => $providerReference,
                'status' => $payload['status'] ?? 'CREATED',
                'approvalUrl' => $this->extractApprovalUrl($payload),
                'raw' => $payload,
            ],
        ];
    }

    public function confirm(string $providerReference, array $payload = []): array
    {
        $accessToken = $this->fetchAccessToken();

        try {
            $response = $this->httpClient->request('POST', $this->buildUrl(sprintf('/v2/checkout/orders/%s/capture', trim($providerReference))), [
                'headers' => [
                    'Authorization' => sprintf('Bearer %s', $accessToken),
                    'Content-Type' => 'application/json',
                ],
            ]);
            $responsePayload = $response->toArray(false);
        } catch (ExceptionInterface) {
            return [
                'provider' => 'paypal',
                'providerReference' => trim($providerReference),
                'status' => 'failed',
                'payload' => [
                    'error' => 'paypal_confirm_failed',
                ],
            ];
        }

        return [
            'provider' => 'paypal',
            'providerReference' => trim($providerReference),
            'status' => $this->mapPayPalStatus((string) ($responsePayload['status'] ?? '')),
            'payload' => $responsePayload,
        ];
    }

    public function verifyWebhook(array $payload, ?string $signature = null): ?array
    {
        if (trim($this->webhookSecret) !== '') {
            if (!is_string($signature) || trim($signature) === '') {
                return null;
            }

            $encodedPayload = json_encode($payload);
            if (!is_string($encodedPayload)) {
                return null;
            }

            $expectedSignature = hash_hmac('sha256', $encodedPayload, $this->webhookSecret);
            if (!hash_equals($expectedSignature, strtolower(trim($signature)))) {
                return null;
            }
        }

        $resource = $payload['resource'] ?? null;
        if (!is_array($resource)) {
            return null;
        }

        $providerReference = $resource['id'] ?? ($resource['supplementary_data']['related_ids']['order_id'] ?? null);
        $eventId = $payload['id'] ?? null;

        if (!is_string($providerReference) || !is_string($eventId)) {
            return null;
        }

        $eventType = is_string($payload['event_type'] ?? null) ? (string) $payload['event_type'] : '';

        return [
            'provider' => 'paypal',
            'providerReference' => trim($providerReference),
            'status' => $this->mapWebhookStatus($eventType, $resource),
            'webhookKey' => $this->buildWebhookKey(trim($eventId)),
            'payload' => $payload,
        ];
    }

    private function fetchAccessToken(): string
    {
        try {
            $response = $this->httpClient->request('POST', $this->buildUrl('/v1/oauth2/token'), [
                'headers' => [
                    'Authorization' => sprintf('Basic %s', base64_encode(sprintf('%s:%s', $this->clientId, $this->clientSecret))),
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);
            $payload = $response->toArray(false);
        } catch (ExceptionInterface) {
            return '';
        }

        return is_string($payload['access_token'] ?? null) ? (string) $payload['access_token'] : '';
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function extractApprovalUrl(array $payload): ?string
    {
        $links = $payload['links'] ?? null;
        if (!is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }

            if (($link['rel'] ?? null) === 'approve' && is_string($link['href'] ?? null)) {
                return (string) $link['href'];
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $resource
     */
    private function mapWebhookStatus(string $eventType, array $resource): string
    {
        return match ($eventType) {
            'PAYMENT.CAPTURE.COMPLETED', 'CHECKOUT.ORDER.APPROVED', 'CHECKOUT.ORDER.COMPLETED' => 'succeeded',
            'PAYMENT.CAPTURE.DENIED', 'PAYMENT.CAPTURE.DECLINED', 'CHECKOUT.ORDER.VOIDED' => 'failed',
            default => $this->mapPayPalStatus((string) ($resource['status'] ?? '')),
        };
    }

    private function mapPayPalStatus(string $status): string
    {
        $normalizedStatus = strtoupper(trim($status));

        return match ($normalizedStatus) {
            'COMPLETED' => 'succeeded',
            'FAILED', 'DENIED', 'VOIDED' => 'failed',
            'CREATED', 'APPROVED', 'PAYER_ACTION_REQUIRED', 'SAVED', 'PENDING' => 'requires_confirmation',
            default => 'created',
        };
    }

    private function buildWebhookKey(string $eventId): string
    {
        return hash('sha256', 'paypal_' . $eventId);
    }

    private function buildUrl(string $path): string
    {
        $baseUrl = trim($this->apiBaseUrl);

        return sprintf('%s%s', rtrim($baseUrl, '/'), $path);
    }
}
