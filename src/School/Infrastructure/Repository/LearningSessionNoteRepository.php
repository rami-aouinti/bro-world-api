<?php

declare(strict_types=1);

namespace App\School\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\School\Domain\Entity\LearningSessionNote;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends BaseRepository<LearningSessionNote>
 */
final class LearningSessionNoteRepository extends BaseRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LearningSessionNote::class);
    }
}
