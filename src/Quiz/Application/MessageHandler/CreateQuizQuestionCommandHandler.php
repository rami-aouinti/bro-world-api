<?php

declare(strict_types=1);

namespace App\Quiz\Application\MessageHandler;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\Configuration\Infrastructure\Repository\ConfigurationRepository;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\Quiz\Application\Message\CreateQuizQuestionCommand;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class CreateQuizQuestionCommandHandler
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuizQuestionRepository $questionRepository,
        private ApplicationRepository $applicationRepository,
        private ConfigurationRepository $configurationRepository,
    ) {}

    public function __invoke(CreateQuizQuestionCommand $command): void
    {
        $application = $this->applicationRepository->findOneBy(['slug' => $command->applicationSlug]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application not found.');
        }

        $quiz = $this->quizRepository->findOneByApplication($application);
        if (!$quiz instanceof Quiz) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Quiz not found for application.');
        }

        if ($quiz->getOwner()->getId() !== $command->actorUserId) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only application owner can create quiz questions.');
        }

        $question = (new QuizQuestion())
            ->setQuiz($quiz)
            ->setTitle($command->title)
            ->setLevel($command->level)
            ->setCategory($command->category);

        foreach ($command->answers as $answerItem) {
            $answer = (new QuizAnswer())
                ->setQuestion($question)
                ->setLabel((string) ($answerItem['label'] ?? ''))
                ->setCorrect((bool) ($answerItem['correct'] ?? false));
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
    }
}
