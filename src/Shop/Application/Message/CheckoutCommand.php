<?php

declare(strict_types=1);

namespace App\Shop\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CheckoutCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $shopId,
        public string $userId,
        public string $billingAddress,
        public string $shippingAddress,
        public string $email,
        public string $phone,
        public string $shippingMethod,
    ) {
    }
}

