<?php

declare(strict_types=1);

namespace App\Blog\Domain\Enum;

enum BlogStatus: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
}
