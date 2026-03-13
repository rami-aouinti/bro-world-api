<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

final class MoneyFormatter
{
    public static function toApiAmount(int $amountInCents): float
    {
        return round($amountInCents / 100, 2);
    }
}
