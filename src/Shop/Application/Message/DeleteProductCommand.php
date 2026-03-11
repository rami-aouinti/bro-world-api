<?php

declare(strict_types=1);

namespace App\Shop\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class DeleteProductCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $productId,
    ) {
    }
}
