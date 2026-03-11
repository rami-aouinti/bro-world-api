<?php

declare(strict_types=1);

namespace App\Crm\Domain\Enum;

enum TaskRequestStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
}
