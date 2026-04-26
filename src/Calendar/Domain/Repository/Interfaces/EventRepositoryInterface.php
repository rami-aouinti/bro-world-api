<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Repository\Interfaces;

use App\Calendar\Domain\Entity\Event;
use App\User\Domain\Entity\User;
use DateTimeImmutable;

interface EventRepositoryInterface
{
    /**
     * @return array<int, Event>
     */
    public function findByUser(
        User $user,
        array $filters = [],
        int $page = 1,
        int $limit = 20,
        ?array $esIds = null,
        ?DateTimeImmutable $startAtFrom = null,
        ?DateTimeImmutable $startAtTo = null,
    ): array;

    public function countByUser(
        User $user,
        array $filters = [],
        ?array $esIds = null,
        ?DateTimeImmutable $startAtFrom = null,
        ?DateTimeImmutable $startAtTo = null,
    ): int;

    /**
     * @return array<int, Event>
     */
    public function findByApplicationSlug(string $applicationSlug, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array;

    public function countByApplicationSlug(string $applicationSlug, array $filters = [], ?array $esIds = null): int;

    /**
     * @return array<int, Event>
     */
    public function findAllByApplicationSlug(string $applicationSlug, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array;

    public function countAllByApplicationSlug(string $applicationSlug, array $filters = [], ?array $esIds = null): int;

    /**
     * @return array<int, Event>
     */
    public function findByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], int $page = 1, int $limit = 20, ?array $esIds = null): array;

    public function countByApplicationSlugAndUser(string $applicationSlug, User $user, array $filters = [], ?array $esIds = null): int;

    /**
     * @return array<int, Event>
     */
    public function findUpcomingPrivateByUser(User $user, int $limit = 3): array;

    /**
     * @return array<int, Event>
     */
    public function findUpcomingByApplicationSlugAndUser(string $applicationSlug, User $user, int $limit = 3): array;
}
