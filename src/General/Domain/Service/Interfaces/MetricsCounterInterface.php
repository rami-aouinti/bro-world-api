<?php

declare(strict_types=1);

namespace App\General\Domain\Service\Interfaces;

interface MetricsCounterInterface
{
    /**
     * @param array<string, string> $labels
     */
    public function increment(string $name, array $labels = [], int $value = 1): void;
}

