<?php

declare(strict_types=1);

namespace App\Blog\Domain\Enum;

enum BlogVisibility: string
{
    case PUBLIC = 'public';
    case PRIVATE = 'private';
}
