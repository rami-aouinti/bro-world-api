<?php

declare(strict_types=1);

namespace App\Tool\Application\DTO\Warmup;

final readonly class WarmupEndpointConfig
{
    public function __construct(
        public string $path,
        public bool $critical,
        public ?float $successThresholdMs,
    ) {
    }
}
