<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAttempt;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class QuizAttemptRepository extends BaseRepository
{
    protected static string $entityName = QuizAttempt::class;
    protected static array $searchColumns = ['id'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return list<QuizAttempt>
     */
    public function findRecentByQuizAndUser(Quiz $quiz, User $user): array
    {
        return $this->createQueryBuilder('attempt')
            ->andWhere('attempt.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->andWhere('attempt.user = :user')->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('attempt.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{attemptCount:int, passedCount:int, averageScore:float|null}
     */
    public function getStatsByQuiz(Quiz $quiz): array
    {
        $result = $this->createQueryBuilder('attempt')
            ->select('COUNT(attempt.id) AS attemptCount')
            ->addSelect('COALESCE(SUM(CASE WHEN attempt.passed = true THEN 1 ELSE 0 END), 0) AS passedCount')
            ->addSelect('AVG(attempt.score) AS averageScore')
            ->andWhere('attempt.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleResult();

        return [
            'attemptCount' => (int)($result['attemptCount'] ?? 0),
            'passedCount' => (int)($result['passedCount'] ?? 0),
            'averageScore' => isset($result['averageScore']) ? (float)$result['averageScore'] : null,
        ];
    }
}
