<?php

declare(strict_types=1);

namespace App\Quiz\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizAttempt;
use App\Quiz\Domain\Entity\QuizAttemptAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Infrastructure\Repository\QuizAttemptAnswerRepository;
use App\Quiz\Infrastructure\Repository\QuizAttemptRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_fill_keys;
use function array_intersect_key;
use function array_key_exists;
use function array_keys;
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
        private QuizAttemptRepository $quizAttemptRepository,
        private QuizAttemptAnswerRepository $quizAttemptAnswerRepository,
        private QuizCacheService $quizCacheService,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function submitByApplicationSlug(string $applicationSlug, array $payload, User $loggedInUser): array
    {
        $quiz = $this->quizRepository->findPublishedByApplicationSlugWithConfiguration($applicationSlug);

        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for this application.');
        }

        if (!array_key_exists('answers', $payload) || !is_array($payload['answers'])) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "answers" must be an array.');
        }

        $quizData = $this->quizReadService->getCorrectionByApplicationSlug($applicationSlug, null, null, false);
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

        $evaluatedQuestionById = array_intersect_key(
            $questionById,
            array_fill_keys(array_keys($submittedAnswersByQuestionId), true)
        );

        $totalPoints = 0;
        $earnedPoints = 0;
        $correctAnswers = 0;
        $results = [];

        foreach ($evaluatedQuestionById as $questionId => $question) {
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

        $attempt = new QuizAttempt()
            ->setQuiz($quiz)
            ->setUser($loggedInUser)
            ->setScore($score)
            ->setPassed($score >= $quiz->getPassScore())
            ->setTotalQuestions(count($evaluatedQuestionById))
            ->setCorrectAnswers($correctAnswers);
        $this->quizAttemptRepository->save($attempt, false);

        foreach ($results as $result) {
            $question = $this->quizRepository->getEntityManager()->getRepository(QuizQuestion::class)
                ->find($result['questionId']);
            if (!$question instanceof QuizQuestion) {
                continue;
            }

            $selectedAnswer = null;
            if (is_string($result['selectedAnswerId'])) {
                $selectedAnswerEntity = $this->quizRepository->getEntityManager()->getRepository(QuizAnswer::class)
                    ->find($result['selectedAnswerId']);
                if ($selectedAnswerEntity instanceof QuizAnswer) {
                    $selectedAnswer = $selectedAnswerEntity;
                }
            }

            $attemptAnswer = new QuizAttemptAnswer()
                ->setAttempt($attempt)
                ->setQuestion($question)
                ->setSelectedAnswer($selectedAnswer)
                ->setIsCorrect((bool)$result['isCorrect']);
            $this->quizAttemptAnswerRepository->save($attemptAnswer, false);
        }

        $this->quizAttemptRepository->getEntityManager()->flush();
        $this->quizCacheService->invalidateByApplicationSlug($applicationSlug);

        return [
            'attemptId' => $attempt->getId(),
            'quizId' => $quiz->getId(),
            'applicationSlug' => $applicationSlug,
            'passScore' => $quiz->getPassScore(),
            'score' => $score,
            'passed' => $score >= $quiz->getPassScore(),
            'totalQuestions' => count($evaluatedQuestionById),
            'answeredQuestions' => count($submittedAnswersByQuestionId),
            'correctAnswers' => $correctAnswers,
            'totalPoints' => $totalPoints,
            'earnedPoints' => $earnedPoints,
            'results' => $results,
        ];
    }
}
