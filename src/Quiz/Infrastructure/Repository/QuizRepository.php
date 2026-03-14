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
    protected static array $searchColumns = ['id', 'title', 'description'];

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

    public function findOneByApplicationSlugWithConfiguration(string $slug): ?Quiz
    {
        $result = $this->createQueryBuilder('quiz')
            ->leftJoin('quiz.application', 'application')
            ->leftJoin('quiz.configuration', 'configuration')->addSelect('configuration')
            ->andWhere('application.slug = :slug')->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Quiz ? $result : null;
    }

    public function findPublishedByApplicationSlugWithConfiguration(string $slug): ?Quiz
    {
        $result = $this->createQueryBuilder('quiz')
            ->leftJoin('quiz.application', 'application')
            ->leftJoin('quiz.configuration', 'configuration')->addSelect('configuration')
            ->andWhere('application.slug = :slug')->setParameter('slug', $slug)
            ->andWhere('quiz.isPublished = true')
            ->getQuery()
            ->getOneOrNullResult();

        return $result instanceof Quiz ? $result : null;
    }
}
