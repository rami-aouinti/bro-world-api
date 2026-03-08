<?php

declare(strict_types=1);

namespace App\Platform\Domain\Repository\Interfaces;

use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Query;

interface ApplicationRepositoryInterface
{
    /**
     * @param array<string, string> $filters
     * @param array<int, string>|null $esIds
     */
    public function createListQuery(array $filters, ?User $loggedInUser, ?array $esIds, int $page, int $limit): Query;

    /**
     * @param array<string, string> $filters
     * @param array<int, string>|null $esIds
     */
    public function countList(array $filters, ?User $loggedInUser, ?array $esIds): int;
}
