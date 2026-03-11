<?php

declare(strict_types=1);

namespace App\Shop\Domain\Enum;

enum PaymentStatus: string
{
    case CREATED = 'created';
    case REQUIRES_CONFIRMATION = 'requires_confirmation';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';
}
