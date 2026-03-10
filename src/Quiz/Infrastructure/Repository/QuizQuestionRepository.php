<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\QuizQuestion;
use Doctrine\Persistence\ManagerRegistry;

class QuizQuestionRepository extends BaseRepository
{
    protected static string $entityName = QuizQuestion::class;
    protected static array $searchColumns = ['id', 'title', 'category'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }
}
