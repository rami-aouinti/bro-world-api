<?php

declare(strict_types=1);

namespace App\Shop\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateProductCommand implements MessageHighInterface
{
    /**
     * @param array<int, string> $tagIds
     */
    public function __construct(
        public string $operationId,
        public string $name,
        public int $price,
        public ?string $shopId = null,
        public ?string $categoryId = null,
        public array $tagIds = [],
        public ?string $applicationSlug = null,
    ) {
    }
}
