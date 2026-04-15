<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\Shop\Domain\Service\Interfaces\PaymentProviderInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_keys;
use function implode;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

final readonly class PaymentProviderRouter
{
    /**
     * @var array<string, PaymentProviderInterface>
     */
    private array $providers;

    /**
     * @param iterable<string, PaymentProviderInterface> $providers
     */
    public function __construct(iterable $providers)
    {
        $normalizedProviders = [];
        foreach ($providers as $key => $provider) {
            if (!is_string($key)) {
                continue;
            }

            $normalizedProviders[strtolower(trim($key))] = $provider;
        }

        $this->providers = $normalizedProviders;
    }

    public function getProvider(string $providerKey): PaymentProviderInterface
    {
        $normalizedProviderKey = strtolower(trim($providerKey));
        $provider = $this->providers[$normalizedProviderKey] ?? null;
        if ($provider instanceof PaymentProviderInterface) {
            return $provider;
        }

        throw new HttpException(
            JsonResponse::HTTP_BAD_REQUEST,
            sprintf(
                'Unsupported payment provider "%s". Supported providers: %s.',
                $providerKey,
                implode(', ', $this->getSupportedProviderKeys()),
            ),
        );
    }

    public function resolveProviderKey(?string $provider = null, ?string $paymentMethod = null, string $defaultProvider = 'mock'): string
    {
        $resolved = $provider ?? $paymentMethod ?? $defaultProvider;

        return strtolower(trim($resolved));
    }

    /**
     * @return list<string>
     */
    public function getSupportedProviderKeys(): array
    {
        return array_keys($this->providers);
    }
}
