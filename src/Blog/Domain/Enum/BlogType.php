<?php

declare(strict_types=1);

namespace App\Blog\Domain\Enum;

enum BlogType: string
{
    case GENERAL = 'general';
    case APPLICATION = 'application';
}
