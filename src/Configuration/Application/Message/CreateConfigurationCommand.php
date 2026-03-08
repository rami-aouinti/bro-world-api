<?php

declare(strict_types=1);

namespace App\Configuration\Application\Message;

use App\Configuration\Application\DTO\Configuration\ConfigurationCreate;
use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class CreateConfigurationCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public ConfigurationCreate $dto,
    ) {
    }
}
