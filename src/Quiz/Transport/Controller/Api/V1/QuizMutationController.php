<?php

declare(strict_types=1);

namespace App\Quiz\Transport\Controller\Api\V1;

use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Quiz\Application\Service\QuizCacheService;
use App\Quiz\Application\Service\QuizEditorAccessService;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use App\Quiz\Infrastructure\Repository\QuizAnswerRepository;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_values;
use function count;
use function is_array;
use function is_bool;
use function is_string;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Quiz')]
final readonly class QuizMutationController
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuizQuestionRepository $questionRepository,
        private QuizAnswerRepository $answerRepository,
        private ApplicationRepository $applicationRepository,
        private QuizEditorAccessService $accessService,
        private QuizCacheService $quizCacheService,
    ) {
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/applications/{applicationSlug}', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create quiz for application', tags: ['Quiz'])]
    public function createQuiz(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $application = $this->findApplication($applicationSlug);

        if ($this->quizRepository->findOneByApplication($application) instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Quiz already exists for this application.');
        }

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $quiz = (new Quiz())
            ->setApplication($application)
            ->setOwner($loggedInUser)
            ->setTitle((string)($payload['title'] ?? 'Application quiz'))
            ->setDescription((string)($payload['description'] ?? ''))
            ->setPassScore((int)($payload['passScore'] ?? 70));

        $this->quizRepository->save($quiz);
        $this->quizCacheService->invalidateByApplicationSlug($applicationSlug);

        return new JsonResponse(['id' => $quiz->getId()], JsonResponse::HTTP_CREATED);
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/applications/{applicationSlug}', methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update quiz metadata', tags: ['Quiz'])]
    public function updateQuiz(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $quiz = $this->findQuizByApplicationSlug($applicationSlug);
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $quiz
            ->setTitle((string)($payload['title'] ?? $quiz->getTitle()))
            ->setDescription((string)($payload['description'] ?? $quiz->getDescription()))
            ->setPassScore((int)($payload['passScore'] ?? $quiz->getPassScore()));

        $this->quizRepository->save($quiz);
        $this->quizCacheService->invalidateByApplicationSlug($applicationSlug);

        return new JsonResponse(['id' => $quiz->getId()]);
    }

    #[Route('/v1/quiz/applications/{applicationSlug}/publish', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Publish quiz', tags: ['Quiz'])]
    public function publishQuiz(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        return $this->toggleQuizPublication($applicationSlug, $loggedInUser, true);
    }

    #[Route('/v1/quiz/applications/{applicationSlug}/unpublish', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Unpublish quiz', tags: ['Quiz'])]
    public function unpublishQuiz(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        return $this->toggleQuizPublication($applicationSlug, $loggedInUser, false);
    }

    #[Route('/v1/quiz/applications/{applicationSlug}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete quiz', tags: ['Quiz'])]
    public function deleteQuiz(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        $quiz = $this->findQuizByApplicationSlug($applicationSlug);
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $this->quizRepository->remove($quiz);
        $this->quizCacheService->invalidateByApplicationSlug($applicationSlug);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/questions/{questionId}', methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update quiz question', tags: ['Quiz'])]
    public function updateQuestion(string $questionId, Request $request, User $loggedInUser): JsonResponse
    {
        $question = $this->findQuestion($questionId);
        $quiz = $question->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $question
            ->setTitle((string)($payload['title'] ?? $question->getTitle()))
            ->setLevel(QuizLevel::fromString((string)($payload['level'] ?? $question->getLevel()->value)))
            ->setCategory(QuizCategory::fromString((string)($payload['category'] ?? $question->getCategory()->value)))
            ->setPoints((int)($payload['points'] ?? $question->getPoints()))
            ->setExplanation(is_string($payload['explanation'] ?? null) ? $payload['explanation'] : $question->getExplanation());

        $this->questionRepository->save($question);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(['id' => $question->getId()]);
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/questions/{questionId}/reorder', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Reorder quiz question', tags: ['Quiz'])]
    public function reorderQuestion(string $questionId, Request $request, User $loggedInUser): JsonResponse
    {
        $question = $this->findQuestion($questionId);
        $quiz = $question->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $question->setPosition((int)($payload['position'] ?? $question->getPosition()));

        $this->questionRepository->save($question);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(['id' => $question->getId(), 'position' => $question->getPosition()]);
    }

    #[Route('/v1/quiz/questions/{questionId}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete quiz question', tags: ['Quiz'])]
    public function deleteQuestion(string $questionId, User $loggedInUser): JsonResponse
    {
        $question = $this->findQuestion($questionId);
        $quiz = $question->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $this->questionRepository->remove($question);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/answers/{answerId}', methods: [Request::METHOD_PUT])]
    #[OA\Put(summary: 'Update quiz answer', tags: ['Quiz'])]
    public function updateAnswer(string $answerId, Request $request, User $loggedInUser): JsonResponse
    {
        $answer = $this->findAnswer($answerId);
        $quiz = $answer->getQuestion()->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $answer
            ->setLabel((string)($payload['label'] ?? $answer->getLabel()))
            ->setCorrect(is_bool($payload['correct'] ?? null) ? $payload['correct'] : $answer->isCorrect())
            ->setPosition((int)($payload['position'] ?? $answer->getPosition()));

        $this->answerRepository->save($answer);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(['id' => $answer->getId()]);
    }

    /** @throws JsonException */
    #[Route('/v1/quiz/answers/{answerId}/reorder', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Reorder quiz answer', tags: ['Quiz'])]
    public function reorderAnswer(string $answerId, Request $request, User $loggedInUser): JsonResponse
    {
        $answer = $this->findAnswer($answerId);
        $quiz = $answer->getQuestion()->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $answer->setPosition((int)($payload['position'] ?? $answer->getPosition()));

        $this->answerRepository->save($answer);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(['id' => $answer->getId(), 'position' => $answer->getPosition()]);
    }

    #[Route('/v1/quiz/answers/{answerId}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(summary: 'Delete quiz answer', tags: ['Quiz'])]
    public function deleteAnswer(string $answerId, User $loggedInUser): JsonResponse
    {
        $answer = $this->findAnswer($answerId);
        $quiz = $answer->getQuestion()->getQuiz();
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        if (count(array_values($answer->getQuestion()->getAnswers()->toArray())) <= 2) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'A question must keep at least two answers.');
        }

        $this->answerRepository->remove($answer);
        $this->quizCacheService->invalidateByApplicationSlug($quiz->getApplication()->getSlug());

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function findApplication(string $applicationSlug): Application
    {
        $application = $this->applicationRepository->findOneBy(['slug' => $applicationSlug]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application not found.');
        }

        return $application;
    }

    private function findQuizByApplicationSlug(string $applicationSlug): Quiz
    {
        $quiz = $this->quizRepository->findOneByApplicationSlugWithConfiguration($applicationSlug);
        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found.');
        }

        return $quiz;
    }

    private function findQuestion(string $questionId): QuizQuestion
    {
        $question = $this->questionRepository->find($questionId);
        if (!$question instanceof QuizQuestion) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Question not found.');
        }

        return $question;
    }

    private function findAnswer(string $answerId): QuizAnswer
    {
        $answer = $this->answerRepository->find($answerId);
        if (!$answer instanceof QuizAnswer) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Answer not found.');
        }

        return $answer;
    }

    private function toggleQuizPublication(string $applicationSlug, User $loggedInUser, bool $published): JsonResponse
    {
        $quiz = $this->findQuizByApplicationSlug($applicationSlug);
        $this->accessService->assertCanEdit($quiz, $loggedInUser);

        $quiz->setPublished($published);
        $this->quizRepository->save($quiz);
        $this->quizCacheService->invalidateByApplicationSlug($applicationSlug);

        return new JsonResponse(['id' => $quiz->getId(), 'isPublished' => $quiz->isPublished()]);
    }
}
