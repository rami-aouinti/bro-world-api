<?php

declare(strict_types=1);

namespace App\Tool\Application\DTO\Warmup;

final readonly class WarmupPublicEndpointsConfig
{
    /**
     * @param list<WarmupEndpointConfig> $endpoints
     */
    public function __construct(
        public array $endpoints,
        public int $maxConcurrency,
        public float $timeoutSeconds,
        public int $retryMax,
        public ?float $successThresholdMs,
    ) {
    }
}
