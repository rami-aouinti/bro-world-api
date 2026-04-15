<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Payment;

use Symfony\Component\Validator\Constraints as Assert;

final class CreatePaymentIntentInput
{
    #[Assert\Choice(choices: ['paypal', 'stripe', 'mock'], message: 'provider must be one of: paypal, stripe, mock.')]
    public ?string $provider = null;

    #[Assert\Choice(choices: ['paypal', 'stripe', 'mock'], message: 'paymentMethod must be one of: paypal, stripe, mock.')]
    public ?string $paymentMethod = null;

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $input = new self();

        $provider = isset($payload['provider']) ? trim((string) $payload['provider']) : null;
        $paymentMethod = isset($payload['paymentMethod']) ? trim((string) $payload['paymentMethod']) : null;

        $input->provider = $provider !== '' ? strtolower($provider) : null;
        $input->paymentMethod = $paymentMethod !== '' ? strtolower($paymentMethod) : null;

        return $input;
    }
}
