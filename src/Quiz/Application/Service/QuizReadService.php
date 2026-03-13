<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

    private const QUIZ_STATS_CACHE_TTL = 120;
        $cacheKey = sprintf('quiz_stats_%s', $slug);
        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug): array {
            $item->expiresAfter(self::QUIZ_STATS_CACHE_TTL);
            $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($slug);
            if ($quiz === null) {
                return [];
            }

            $stats = $this->quizQuestionRepository->getQuizStats($quiz);

            return [
                'questionCount' => $stats['questionCount'],
                'answerCount' => $stats['answerCount'],
                'averageAnswersPerQuestion' => $stats['questionCount'] > 0
                    ? round($stats['answerCount'] / $stats['questionCount'], 2)
                    : 0.0,
                'totalPoints' => $stats['totalPoints'],
            ];
        });

    /**
     * @throws InvalidArgumentException
     */
    public function getByApplicationSlug(string $slug, ?string $level = null, ?string $category = null): array
    {
        $cacheKey = sprintf('quiz_%s_%s_%s', $slug, (string)$level, (string)$category);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($slug, $level, $category): array {
            $item->expiresAfter(self::QUIZ_CACHE_TTL);
            $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($slug);

            if (!$quiz instanceof Quiz) {
                return [];
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
                    'answers' => array_map(static fn ($a): array => [
                        'id' => $a->getId(),
                        'label' => $a->getLabel(),
                        'correct' => $a->isCorrect(),
                        'position' => $a->getPosition(),
                    ], $q->getAnswers()->toArray()),
                ], $questions),
            ];
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getStatsByApplicationSlug(string $slug): array
    {
        $quiz = $this->getByApplicationSlug($slug);
        if ($quiz === []) {
            return [];
        }

        $questionCount = 0;
        $answerCount = 0;
        $totalPoints = 0;

        foreach ($quiz['questions'] as $question) {
            ++$questionCount;
            $answerCount += count($question['answers']);
            $totalPoints += (int)$question['points'];
        }

        return [
            'questionCount' => $questionCount,
            'answerCount' => $answerCount,
            'averageAnswersPerQuestion' => $questionCount > 0 ? round($answerCount / $questionCount, 2) : 0.0,
            'totalPoints' => $totalPoints,
        ];
    }
}
