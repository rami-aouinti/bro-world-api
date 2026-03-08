<?php

declare(strict_types=1);

namespace App\Configuration\Application\Message;

use App\Configuration\Application\DTO\Configuration\ConfigurationPatch;
use App\General\Domain\Message\Interfaces\MessageHighInterface;

final readonly class PatchConfigurationCommand implements MessageHighInterface
{
    public function __construct(
        public string $operationId,
        public string $id,
        public ConfigurationPatch $dto,
    ) {
    }
}
