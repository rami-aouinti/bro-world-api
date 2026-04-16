<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\User\Domain\Entity\User;

readonly class GeneralMyJobListService
{
    public function __construct(
        private MyJobListService $myJobListService,
    ) {
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function getList(User $loggedInUser): array
    {
        return $this->myJobListService->getList($loggedInUser);
    }
}
