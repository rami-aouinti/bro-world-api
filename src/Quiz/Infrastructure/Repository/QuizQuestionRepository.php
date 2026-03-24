<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizCategory;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizLevel;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class QuizQuestionRepository extends BaseRepository
{
    protected static string $entityName = QuizQuestion::class;
    protected static array $searchColumns = ['id', 'title', 'level'];

    public function __construct(
        protected ManagerRegistry $managerRegistry
    ) {
    }

    /**
     * @return list<QuizQuestion>
     */
    public function findByFilters(Quiz $quiz, ?QuizLevel $level = null, ?QuizCategory $category = null): array
    {
        $queryBuilder = $this->createQueryBuilder('question')
            ->leftJoin('question.answers', 'answer')->addSelect('answer')
            ->andWhere('question.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('question.position', 'ASC')
            ->addOrderBy('answer.position', 'ASC');

        if ($level instanceof QuizLevel) {
            $queryBuilder->andWhere('question.level = :level')->setParameter('level', $level);
        }

        if ($category instanceof QuizCategory) {
            $queryBuilder->andWhere('question.category = :category')->setParameter('category', $category->getId(), UuidBinaryOrderedTimeType::NAME);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * @return array{questionCount:int, answerCount:int, totalPoints:int}
     */
    public function getQuizStats(Quiz $quiz): array
    {
        $questionStats = $this->createQueryBuilder('question')
            ->select('COUNT(question.id) AS questionCount, COALESCE(SUM(question.points), 0) AS totalPoints')
            ->andWhere('question.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleResult();

        $answerCount = (int)$this->createQueryBuilder('question')
            ->select('COUNT(answer.id)')
            ->leftJoin('question.answers', 'answer')
            ->andWhere('question.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'questionCount' => (int)($questionStats['questionCount'] ?? 0),
            'answerCount' => $answerCount,
            'totalPoints' => (int)($questionStats['totalPoints'] ?? 0),
        ];
    }

    public function nextPositionForQuiz(Quiz $quiz): int
    {
        $maxPosition = $this->createQueryBuilder('question')
            ->select('MAX(question.position)')
            ->andWhere('question.quiz = :quiz')->setParameter('quiz', $quiz->getId(), UuidBinaryOrderedTimeType::NAME)
            ->getQuery()
            ->getSingleScalarResult();

        return ((int)$maxPosition) + 1;
    }
}
