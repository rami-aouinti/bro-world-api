<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use function array_change_key_case;
use function is_string;
use function strtolower;
use function trim;

final readonly class CrmGithubOwnerResolver
{
    /**
     * @param array<string,string> $ownersByApplicationSlug
     */
    public function __construct(
        private string $defaultOwner,
        private array $ownersByApplicationSlug = [],
    ) {
    }

    public function resolve(string $applicationSlug): string
    {
        $normalizedSlug = strtolower(trim($applicationSlug));
        $normalizedOwnersBySlug = array_change_key_case($this->ownersByApplicationSlug, CASE_LOWER);

        $mappedOwner = $normalizedOwnersBySlug[$normalizedSlug] ?? null;
        if (is_string($mappedOwner) && trim($mappedOwner) !== '') {
            return trim($mappedOwner);
        }

        $fallbackOwner = trim($this->defaultOwner);

        return $fallbackOwner !== '' ? $fallbackOwner : 'bro-world';
    }
}
