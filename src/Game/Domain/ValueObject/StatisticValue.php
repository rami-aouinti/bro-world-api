<?php

declare(strict_types=1);

namespace App\Game\Domain\ValueObject;

readonly class StatisticValue
{
    public function __construct(private float $value)
    {
    }

    public function toFloat(): float
    {
        return $this->value;
    }
}
