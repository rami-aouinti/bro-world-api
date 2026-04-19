<?php

declare(strict_types=1);

namespace App\General\Domain\Doctrine\DBAL\Types;

use App\General\Domain\Enum\Language;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Override;

/**
 * @package App\General
 */
class EnumLanguageType extends EnumType
{
    protected static string $name = Types::ENUM_LANGUAGE;
    protected static string $enum = Language::class;

    /**
     * Be tolerant to legacy/dirty values in production data.
     */
    #[Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): Language
    {
        $normalized = strtolower(trim((string)$value));

        if ($normalized === 'uk') {
            $normalized = Language::UA->value;
        }

        return Language::tryFrom($normalized) ?? Language::getDefault();
    }
}
