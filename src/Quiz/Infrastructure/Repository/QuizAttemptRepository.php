<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAttempt;
use App\Quiz\Domain\Enum\QuizLevel;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

use function array_slice;
use function round;
use function usort;

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
     * @return list<array{userId:string,username:string,firstName:string,lastName:string,attemptCount:int,averageWeightedScore:float}>
     */
    public function findTopUsersByWeightedScore(Quiz $quiz, int $limit = 3): array
    {
        $rows = $this->createQueryBuilder('attempt')
            ->select('attempt.id AS attemptId')
            ->addSelect('attempt.score AS score')
            ->addSelect('user.id AS userId')
            ->addSelect('user.username AS username')
            ->addSelect('user.firstName AS firstName')
            ->addSelect('user.lastName AS lastName')
            ->addSelect('AVG(CASE WHEN question.level = :easy THEN 1 WHEN question.level = :medium THEN 2 WHEN question.level = :hard THEN 3 ELSE 1 END) AS levelMultiplier')
            ->leftJoin('attempt.user', 'user')
            ->leftJoin('attempt.answers', 'attemptAnswer')
            ->leftJoin('attemptAnswer.question', 'question')
            ->andWhere('attempt.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('easy', QuizLevel::EASY)
            ->setParameter('medium', QuizLevel::MEDIUM)
            ->setParameter('hard', QuizLevel::HARD)
            ->groupBy('attempt.id, user.id, user.username, user.firstName, user.lastName')
            ->getQuery()
            ->getArrayResult();

        $byUser = [];
        foreach ($rows as $row) {
            $userId = (string)($row['userId'] ?? '');
            if ($userId === '') {
                continue;
            }

            $weightedScore = ((float)($row['score'] ?? 0.0)) * ((float)($row['levelMultiplier'] ?? 1.0));
            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'userId' => $userId,
                    'username' => (string)($row['username'] ?? ''),
                    'firstName' => (string)($row['firstName'] ?? ''),
                    'lastName' => (string)($row['lastName'] ?? ''),
                    'attemptCount' => 0,
                    'totalWeightedScore' => 0.0,
                ];
            }

            $byUser[$userId]['attemptCount']++;
            $byUser[$userId]['totalWeightedScore'] += $weightedScore;
        }

        $items = [];
        foreach ($byUser as $entry) {
            $attemptCount = (int)$entry['attemptCount'];
            $items[] = [
                'userId' => $entry['userId'],
                'username' => $entry['username'],
                'firstName' => $entry['firstName'],
                'lastName' => $entry['lastName'],
                'attemptCount' => $attemptCount,
                'averageWeightedScore' => $attemptCount > 0 ? round(((float)$entry['totalWeightedScore']) / $attemptCount, 2) : 0.0,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['averageWeightedScore'] <=> $a['averageWeightedScore']);

        return array_slice($items, 0, $limit);
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
