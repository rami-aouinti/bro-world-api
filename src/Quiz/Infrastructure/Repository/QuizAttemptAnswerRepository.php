<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\QuizAttemptAnswer;
use Doctrine\Persistence\ManagerRegistry;

class QuizAttemptAnswerRepository extends BaseRepository
{
    protected static string $entityName = QuizAttemptAnswer::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
