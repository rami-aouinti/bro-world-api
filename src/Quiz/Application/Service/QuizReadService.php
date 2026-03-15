<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use App\Quiz\Infrastructure\Repository\QuizAttemptRepository;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

use function is_string;
use function round;

final readonly class QuizReadService
{
    private const QUIZ_CACHE_TTL = 120;
    private const QUIZ_STATS_CACHE_TTL = 120;

    public function __construct(
        private QuizRepository $quizRepository,
        private QuizQuestionRepository $quizQuestionRepository,
        private QuizAttemptRepository $quizAttemptRepository,
        private CacheInterface $cache,
        private QuizCacheService $quizCacheService,
    ) {
    }

    public function getByApplicationSlug(string $slug, ?string $level = null, ?string $category = null): array
    {
        return $this->getQuizProjectionByApplicationSlug($slug, $level, $category, false);
    }

    public function getCorrectionByApplicationSlug(string $slug, ?string $level = null, ?string $category = null): array
    {
        return $this->getQuizProjectionByApplicationSlug($slug, $level, $category, true);
    }

    public function getStatsByApplicationSlug(string $slug): array
    {
        $cacheKey = $this->quizCacheService->buildQuizStatsKey($slug);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug): array {
            $item->expiresAfter(self::QUIZ_STATS_CACHE_TTL);
            $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($slug);
            if ($quiz === null) {
                return [];
            }

            $stats = $this->quizQuestionRepository->getQuizStats($quiz);
            $attemptStats = $this->quizAttemptRepository->getStatsByQuiz($quiz);

            return [
                'questionCount' => $stats['questionCount'],
                'answerCount' => $stats['answerCount'],
                'averageAnswersPerQuestion' => $stats['questionCount'] > 0
                    ? round($stats['answerCount'] / $stats['questionCount'], 2)
                    : 0.0,
                'totalPoints' => $stats['totalPoints'],
                'attemptCount' => $attemptStats['attemptCount'],
                'averageScore' => $attemptStats['averageScore'] !== null ? round((float)$attemptStats['averageScore'], 2) : null,
                'passRate' => $attemptStats['attemptCount'] > 0 ? round(($attemptStats['passedCount'] / $attemptStats['attemptCount']) * 100, 2) : 0.0,
            ];
        });
    }

    private function getQuizProjectionByApplicationSlug(
        string $slug,
        ?string $level,
        ?string $category,
        bool $includeCorrection,
    ): array {
        $cacheKey = $this->quizCacheService->buildQuizReadKey($slug, $level, $category, $includeCorrection);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug, $level, $category, $includeCorrection): array {
            $item->expiresAfter(self::QUIZ_CACHE_TTL);
            $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($slug);

            if (!$quiz instanceof Quiz) {
                return [];
            }

            if (!$quiz->isPublished()) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for this application.');
            }

            $questions = $this->quizQuestionRepository->findByFilters(
                $quiz,
                is_string($level) ? QuizLevel::fromString($level) : null,
                is_string($category) ? QuizCategory::fromString($category) : null,
            );

            return [
                'id' => $quiz->getId(),
                'title' => $quiz->getTitle(),
                'description' => $quiz->getDescription(),
                'passScore' => $quiz->getPassScore(),
                'isPublished' => $quiz->isPublished(),
                'applicationSlug' => $slug,
                'configuration' => $quiz->getConfiguration()?->getConfigurationValue(),
                'questions' => array_map(static fn ($q): array => [
                    'id' => $q->getId(),
                    'title' => $q->getTitle(),
                    'level' => $q->getLevel()->value,
                    'category' => $q->getCategory()->value,
                    'position' => $q->getPosition(),
                    'points' => $q->getPoints(),
                    'explanation' => $q->getExplanation(),
                    'answers' => array_map(
                        static function ($a) use ($includeCorrection): array {
                            $answer = [
                                'id' => $a->getId(),
                                'label' => $a->getLabel(),
                                'position' => $a->getPosition(),
                            ];

                            if ($includeCorrection) {
                                $answer['correct'] = $a->isCorrect();
                            }

                            return $answer;
                        },
                        $q->getAnswers()->toArray()
                    ),
                ], $questions),
            ];
        });
    }
}
