<?php

declare(strict_types=1);

namespace App\User\Domain\Enum;

enum UserRelationshipStatus: string
{
    case PENDING = 'PENDING';
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
    case BLOCKED = 'BLOCKED';
}
