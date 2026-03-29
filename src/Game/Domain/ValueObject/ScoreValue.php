<?php

declare(strict_types=1);

namespace App\Game\Domain\ValueObject;

use InvalidArgumentException;

readonly class ScoreValue
{
    public function __construct(private int $value)
    {
        if ($value < 0) {
            throw new InvalidArgumentException('A score cannot be negative.');
        }
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
