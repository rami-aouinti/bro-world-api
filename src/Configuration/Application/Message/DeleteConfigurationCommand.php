<?php

declare(strict_types=1);

namespace App\Configuration\Application\Message;

use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class DeleteConfigurationCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $id,
    ) {
    }
}
