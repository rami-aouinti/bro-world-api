<?php

declare(strict_types=1);

namespace App\Media\Application\Service;

use function array_map;
use function strtolower;

final class MediaUploadValidationPolicy
{
    /**
     * @param list<string> $allowedMimeTypes
     * @param list<string> $allowedExtensions
     */
    public function __construct(
        private readonly ?int $maxSizeInBytes = null,
        private array $allowedMimeTypes = [],
        private array $allowedExtensions = [],
    ) {
        $this->allowedMimeTypes = array_map(static fn (string $mimeType): string => strtolower($mimeType), $this->allowedMimeTypes);
        $this->allowedExtensions = array_map(static fn (string $extension): string => strtolower($extension), $this->allowedExtensions);
    }

    public function getMaxSizeInBytes(): ?int
    {
        return $this->maxSizeInBytes;
    }

    /**
     * @return list<string>
     */
    public function getAllowedMimeTypes(): array
    {
        return $this->allowedMimeTypes;
    }

    /**
     * @return list<string>
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }
}
