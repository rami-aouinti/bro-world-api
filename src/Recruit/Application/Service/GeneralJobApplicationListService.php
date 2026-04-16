<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\User\Domain\Entity\User;

readonly class GeneralJobApplicationListService
{
    public function __construct(
        private JobApplicationListService $jobApplicationListService,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(User $loggedInUser, ?string $jobId, ?string $jobSlug): array
    {
        return $this->jobApplicationListService->getList($loggedInUser, $jobId, $jobSlug);
    }
}
