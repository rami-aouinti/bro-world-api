<?php

declare(strict_types=1);

namespace App\Chat\Domain\Enum;

enum ChatReactionType: string
{
    case LIKE = 'like';
    case LOVE = 'love';
    case LAUGH = 'laugh';
    case WOW = 'wow';
    case SAD = 'sad';
    case ANGRY = 'angry';

    public const VALUES = [
        self::LIKE->value,
        self::LOVE->value,
        self::LAUGH->value,
        self::WOW->value,
        self::SAD->value,
        self::ANGRY->value,
    ];

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $reaction): string => $reaction->value, self::cases());
    }
}
