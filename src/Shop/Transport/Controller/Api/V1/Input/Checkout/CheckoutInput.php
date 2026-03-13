<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Input\Checkout;

use Symfony\Component\Validator\Constraints as Assert;

final class CheckoutInput
{
    #[Assert\NotBlank(message: 'billingAddress is required.')]
    public string $billingAddress = '';

    #[Assert\NotBlank(message: 'shippingAddress is required.')]
    public string $shippingAddress = '';

    #[Assert\NotBlank(message: 'email is required.')]
    #[Assert\Email(message: 'email must be valid.')]
    public string $email = '';

    #[Assert\NotBlank(message: 'phone is required.')]
    public string $phone = '';

    #[Assert\NotBlank(message: 'shippingMethod is required.')]
    public string $shippingMethod = '';

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        $input = new self();
        $input->billingAddress = trim((string) ($payload['billingAddress'] ?? ''));
        $input->shippingAddress = trim((string) ($payload['shippingAddress'] ?? ''));
        $input->email = trim((string) ($payload['email'] ?? ''));
        $input->phone = trim((string) ($payload['phone'] ?? ''));
        $input->shippingMethod = trim((string) ($payload['shippingMethod'] ?? ''));

        return $input;
    }
}
