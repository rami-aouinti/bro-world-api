<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_fill_keys;
use function array_intersect_key;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function is_string;
use function round;

final readonly class QuizSubmissionService
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuizReadService $quizReadService,
    ) {
    }

    /**
     * @param array<mixed> $payload
     *
     * @return array<string, mixed>
     */
    public function submitByApplicationSlug(string $applicationSlug, array $payload): array
    {
        $quiz = $this->quizRepository->findPublishedByApplicationSlugWithConfiguration($applicationSlug);

        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for this application.');
        }

        if (!array_key_exists('answers', $payload) || !is_array($payload['answers'])) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "answers" must be an array.');
        }

        $quizData = $this->quizReadService->getCorrectionByApplicationSlug($applicationSlug);
        if ($quizData === []) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for this application.');
        }

        $questionById = [];
        foreach ($quizData['questions'] as $question) {
            if (!is_array($question)) {
                continue;
            }

            $questionById[(string)$question['id']] = $question;
        }

        $submittedAnswersByQuestionId = [];

        foreach ($payload['answers'] as $entry) {
            if (!is_array($entry) || !is_string($entry['questionId'] ?? null) || !is_string($entry['answerId'] ?? null)) {
                throw new HttpException(
                    JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                    'Each answer must contain string fields "questionId" and "answerId".'
                );
            }

            $submittedAnswersByQuestionId[$entry['questionId']] = $entry['answerId'];
        }

        $submittedAnswersByQuestionId = array_intersect_key(
            $submittedAnswersByQuestionId,
            array_fill_keys(array_keys($questionById), true)
        );

        $totalPoints = 0;
        $earnedPoints = 0;
        $correctAnswers = 0;
        $results = [];

        foreach ($questionById as $questionId => $question) {
            $questionPoints = (int)($question['points'] ?? 0);
            $totalPoints += $questionPoints;

            $selectedAnswerId = $submittedAnswersByQuestionId[$questionId] ?? null;
            $isCorrect = false;
            $correctAnswerIds = [];

            foreach ($question['answers'] as $answer) {
                if (!is_array($answer)) {
                    continue;
                }

                if (($answer['correct'] ?? false) === true) {
                    $correctAnswerIds[] = (string)$answer['id'];
                }
            }

            if (is_string($selectedAnswerId)) {
                $isCorrect = in_array($selectedAnswerId, $correctAnswerIds, true);
                if ($isCorrect) {
                    $correctAnswers++;
                    $earnedPoints += $questionPoints;
                }
            }

            $results[] = [
                'questionId' => $questionId,
                'selectedAnswerId' => $selectedAnswerId,
                'isCorrect' => $isCorrect,
                'correctAnswerIds' => $correctAnswerIds,
                'points' => $questionPoints,
                'earnedPoints' => $isCorrect ? $questionPoints : 0,
            ];
        }

        $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100, 2) : 0.0;

        return [
            'quizId' => $quiz->getId(),
            'applicationSlug' => $applicationSlug,
            'passScore' => $quiz->getPassScore(),
            'score' => $score,
            'passed' => $score >= $quiz->getPassScore(),
            'totalQuestions' => count($questionById),
            'answeredQuestions' => count($submittedAnswersByQuestionId),
            'correctAnswers' => $correctAnswers,
            'totalPoints' => $totalPoints,
            'earnedPoints' => $earnedPoints,
            'results' => $results,
        ];
    }
}
