<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Infrastructure\Repository\TaskRequestWorklogRepository;

use function max;

final readonly class TaskRequestWorklogReadService
{
    public function __construct(
        private TaskRequestWorklogRepository $worklogRepository,
    ) {
    }

    public function getConsumedHours(string $taskRequestId): float
    {
        return $this->worklogRepository->sumConsumedHoursByTaskRequestId($taskRequestId);
    }

    /**
     * @param list<string> $taskRequestIds
     * @return array<string,float>
     */
    public function getConsumedHoursByTaskRequestIds(array $taskRequestIds): array
    {
        return $this->worklogRepository->sumConsumedHoursByTaskRequestIds($taskRequestIds);
    }

    public function getRemainingHours(float $plannedHours, float $consumedHours): float
    {
        return max(0.0, $plannedHours - $consumedHours);
    }
}
