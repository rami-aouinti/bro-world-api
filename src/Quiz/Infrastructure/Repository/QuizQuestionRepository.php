<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\Repository;

use App\General\Infrastructure\Repository\BaseRepository;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;

class QuizQuestionRepository extends BaseRepository
{
    protected static string $entityName = QuizQuestion::class;
    protected static array $searchColumns = ['id', 'title', 'category', 'level'];

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
            $queryBuilder->andWhere('question.category = :category')->setParameter('category', $category);
        }

        return $queryBuilder->getQuery()->getResult();
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
