<?php

declare(strict_types=1);

namespace App\Blog\Domain\Enum;

enum BlogReactionType: string
{
    case LIKE = 'like';
    case HEART = 'heart';
    case LAUGH = 'laugh';
    case CELEBRATE = 'celebrate';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
