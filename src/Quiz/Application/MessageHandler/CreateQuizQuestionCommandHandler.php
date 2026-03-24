<?php

declare(strict_types=1);

namespace App\Quiz\Application\MessageHandler;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\Quiz\Application\Service\QuizCacheService;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizLevel;
use App\Quiz\Infrastructure\Repository\QuizCategoryRepository;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function count;
use function is_array;
use function is_string;
use function preg_replace;
use function trim;

#[AsMessageHandler]
final readonly class CreateQuizQuestionCommandHandler
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuizQuestionRepository $questionRepository,
        private ApplicationRepository $applicationRepository,
        private ConfigurationRepository $configurationRepository,
        private QuizCategoryRepository $quizCategoryRepository,
        private QuizCacheService $quizCacheService,
    ) {
    }

    public function __invoke(CreateQuizQuestionCommand $command): void
    {
        $application = $this->applicationRepository->findOneBy([
            'slug' => $command->applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application not found.');
        }

        $quiz = $this->quizRepository->findOneByApplication($application);
        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for application.');
        }

        if (count($command->answers) < 2) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'At least two answers are required.');
        }

        $normalizedAnswers = [];
        $correctAnswersCount = 0;

        foreach (array_values($command->answers) as $index => $answerItem) {
            if (!is_array($answerItem)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Each answer must be an object with "label" and "correct" fields.');
            }

            $label = is_string($answerItem['label'] ?? null) ? $answerItem['label'] : '';
            $normalizedLabel = trim((string)preg_replace('/\s+/', ' ', $label));
            if ($normalizedLabel == '') {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Answer labels must be non-empty.');
            }

            $isCorrect = ($answerItem['correct'] ?? false) === true;
            if ($isCorrect) {
                $correctAnswersCount++;
            }

            $normalizedAnswers[] = [
                'label' => $normalizedLabel,
                'correct' => $isCorrect,
                'position' => $index + 1,
            ];
        }

        if ($correctAnswersCount < 1) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'At least one answer must be marked as correct.');
        }

        if ($correctAnswersCount > 1) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Single-choice quizzes require exactly one correct answer per question.');
        }

        $question = (new QuizQuestion())
            ->setQuiz($quiz)
            ->setTitle($command->title)
            ->setLevel(QuizLevel::fromString($command->level))
            ->setCategory($this->quizCategoryRepository->findOneBySlug($command->category) ?? $this->quizCategoryRepository->findOneBySlug('general') ?? throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Quiz category not found.'))
            ->setPoints($command->points)
            ->setExplanation($command->explanation)
            ->setPosition($this->questionRepository->nextPositionForQuiz($quiz));

        foreach ($normalizedAnswers as $answerItem) {
            $answer = (new QuizAnswer())
                ->setQuestion($question)
                ->setLabel($answerItem['label'])
                ->setCorrect($answerItem['correct'])
                ->setPosition($answerItem['position']);
            $this->questionRepository->getEntityManager()->persist($answer);
        }

        if (is_array($command->configuration)) {
            $configuration = (new Configuration())
                ->setApplication($application)
                ->setConfigurationKey('quiz.module.configuration')
                ->setConfigurationValue($command->configuration)
                ->setScope(ConfigurationScope::PLATFORM)
                ->setPrivate(true);
            $this->configurationRepository->save($configuration);
            $quiz->setConfiguration($configuration);
            $this->quizRepository->save($quiz);
        }

        $this->questionRepository->save($question);
        $this->quizCacheService->invalidateByApplicationSlug($command->applicationSlug);
    }
}
