<?php

declare(strict_types=1);

namespace App\General\Infrastructure\Service;

use App\General\Domain\Service\Interfaces\MetricsCounterInterface;

final readonly class NullMetricsCounter implements MetricsCounterInterface
{
    public function increment(string $name, array $labels = [], int $value = 1): void
    {
    }
}
