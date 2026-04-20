<?php

declare(strict_types=1);

namespace App\School\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\Course as Entity;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Course>
 */
final class CourseRepository extends BaseRepository
{
    protected static string $entityName = Entity::class;

    protected static array $searchColumns = [
        'id',
    ];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
