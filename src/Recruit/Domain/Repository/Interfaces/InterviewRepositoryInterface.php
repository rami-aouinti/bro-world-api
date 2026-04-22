<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Repository\Interfaces;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;

interface InterviewRepositoryInterface
{
    /**
     * @return array<int, Interview>
     */
    public function findByApplicationOrdered(Application $application): array;

    /**
     * @param list<string> $applicationIds
     *
     * @return array<string, \DateTimeImmutable>
     */
    public function findFirstInterviewAtByApplicationId(array $applicationIds): array;
}
