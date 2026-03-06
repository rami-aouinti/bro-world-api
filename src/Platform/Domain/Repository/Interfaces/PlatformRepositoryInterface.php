<?php

declare(strict_types=1);

namespace App\Platform\Domain\Repository\Interfaces;

/**
 * @package App\Platform
 */
interface PlatformRepositoryInterface
{
    /**
     * @return array<int, \App\Platform\Domain\Entity\Platform>
     */
    public function findPublicEnabled(): array;
}
