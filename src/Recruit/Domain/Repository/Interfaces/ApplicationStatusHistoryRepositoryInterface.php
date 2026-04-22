<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Repository\Interfaces;

interface ApplicationStatusHistoryRepositoryInterface
{
    /**
     * @param list<string> $applicationIds
     *
     * @return array<string, list<array{toStatus: string, createdAt: \DateTimeImmutable, comment: ?string}>>
     */
    public function findAnalyticsHistoryRowsByApplicationId(array $applicationIds): array;
}
