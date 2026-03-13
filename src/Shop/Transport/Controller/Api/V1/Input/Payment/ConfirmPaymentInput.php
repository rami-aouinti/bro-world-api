<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Payment;

use Symfony\Component\Validator\Constraints as Assert;

final class ConfirmPaymentInput
{
    #[Assert\NotBlank(message: 'providerReference is required.')]
    public string $providerReference = '';

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->providerReference = trim((string)($payload['providerReference'] ?? ''));

        return $input;
    }
}
