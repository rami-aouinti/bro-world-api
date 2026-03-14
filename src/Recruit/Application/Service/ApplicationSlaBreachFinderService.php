<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Infrastructure\Repository\ApplicationRepository;

readonly class ApplicationSlaBreachFinderService
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private ApplicationSlaService $applicationSlaService,
    ) {
    }

    /**
     * @return list<Application>
     */
    public function findAllBreaches(): array
    {
        return $this->applicationRepository->findSlaBreaches($this->applicationSlaService->getRulesInHours());
    }
}
