<?php

declare(strict_types=1);

namespace App\Shop\Domain\Service\Interfaces;

interface PaymentProviderInterface
{
    /**
     * @param array<string, mixed> $metadata
     *
     * @return array{provider:string,providerReference:string,status:string,payload:array<string,mixed>}
     */
    public function createIntent(string $orderId, float $amount, string $currency, array $metadata = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{provider:string,providerReference:string,status:string,payload:array<string,mixed>}
     */
    public function confirm(string $providerReference, array $payload = []): array;

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{provider:string,providerReference:string,status:string,webhookKey:string,payload:array<string,mixed>}|null
     */
    public function verifyWebhook(array $payload, ?string $signature = null): ?array;
}
