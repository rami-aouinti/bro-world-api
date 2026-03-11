<?php

declare(strict_types=1);

namespace App\Shop\Domain\Enum;

enum OrderStatus: string
{
    case DRAFT = 'draft';
    case PENDING_PAYMENT = 'pending_payment';
    case PAID = 'paid';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}

