<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\QuizAnswer;
use Doctrine\Persistence\ManagerRegistry;

class QuizAnswerRepository extends BaseRepository
{
    protected static string $entityName = QuizAnswer::class;
    protected static array $searchColumns = ['id', 'label'];

    public function __construct(protected ManagerRegistry $managerRegistry) {}
}
