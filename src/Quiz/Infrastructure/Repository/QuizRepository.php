<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Platform\Domain\Entity\Application;
use App\Quiz\Domain\Entity\Quiz;
use Doctrine\Persistence\ManagerRegistry;

class QuizRepository extends BaseRepository
{
    protected static string $entityName = Quiz::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    public function findOneByApplication(Application $application): ?Quiz
    {
        $result = $this->findOneBy([
            'application' => $application,
        ]);

        return $result instanceof Quiz ? $result : null;
    }
}
