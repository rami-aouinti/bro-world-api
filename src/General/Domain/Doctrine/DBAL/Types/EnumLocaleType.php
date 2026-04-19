<?php

declare(strict_types=1);

namespace App\General\Domain\Doctrine\DBAL\Types;

use App\General\Domain\Enum\Locale;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;

/**
 * @package App\General
 */
class EnumLocaleType extends EnumType
{
    protected static string $name = Types::ENUM_LOCALE;
    protected static string $enum = Locale::class;

    /**
     * Be tolerant to legacy/dirty values in production data.
     */
    #[Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): Locale
    {
        $normalized = strtolower(trim((string)$value));

        if ($normalized === 'uk') {
            $normalized = Locale::UA->value;
        }

        return Locale::tryFrom($normalized) ?? Locale::getDefault();
    }
}
