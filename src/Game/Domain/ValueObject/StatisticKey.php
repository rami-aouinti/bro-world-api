<?php

declare(strict_types=1);

namespace App\Game\Domain\ValueObject;

use InvalidArgumentException;

use function strtolower;
use function trim;

readonly class StatisticKey
{
    private string $value;

    public function __construct(string $value)
    {
        $normalized = trim(strtolower($value));
        if ($normalized === '') {
            throw new InvalidArgumentException('A statistic key cannot be empty.');
        }

        $this->value = $normalized;
    }

    public function toString(): string
    {
        return $this->value;
    }
}
