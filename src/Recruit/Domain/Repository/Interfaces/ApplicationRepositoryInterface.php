<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Repository\Interfaces;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;

interface ApplicationRepositoryInterface
{
    /**
     * @return list<array{id: string, status: string, createdAt: ?\DateTimeImmutable}>
     */
    public function findAnalyticsApplicationSnapshots(Recruit $recruit, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?Job $job = null): array;

    /**
     * @return array<string, int>
     */
    public function countByCurrentStatusForAnalytics(Recruit $recruit, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?Job $job = null): array;

    /**
     * @return array{APPLIED: int, SCREENING: int, INTERVIEW: int, OFFER_SENT: int, HIRED: int}
     */
    public function countConversionsByStepForAnalytics(Recruit $recruit, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?Job $job = null): array;
}
