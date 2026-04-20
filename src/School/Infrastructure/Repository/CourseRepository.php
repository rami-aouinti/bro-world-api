<?php

declare(strict_types=1);

namespace App\School\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\School\Domain\Entity\Course;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<Course>
 */
final class CourseRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }
}
