<?php

declare(strict_types=1);

namespace App\Platform\Application\Service\PluginProvisioning;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizLevel;
use App\Quiz\Infrastructure\Repository\QuizCategoryRepository;
use App\Quiz\Infrastructure\Repository\QuizQuestionRepository;
use App\Quiz\Infrastructure\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class QuizPluginProvisioner
{
    public function __construct(
        private QuizRepository $quizRepository,
        private QuizQuestionRepository $quizQuestionRepository,
        private QuizCategoryRepository $quizCategoryRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Application $application): void
    {
        $platformKey = $application->getPlatform()?->getPlatformKey();
        if ($platformKey === PlatformKey::SCHOOL || $platformKey === PlatformKey::RECRUIT) {
            return;
        }

        $quiz = $this->quizRepository->findOneByApplication($application);
        if (!$quiz instanceof Quiz) {
            $quiz = (new Quiz())
                ->setApplication($application)
                ->setOwner($application->getUser())
                ->setTitle($application->getTitle() . ' onboarding quiz')
                ->setDescription('Short onboarding quiz generated from plugin provisioning flow.');

            $this->entityManager->persist($quiz);
        }

        $question = $this->quizQuestionRepository->findOneBy([
            'quiz' => $quiz,
            'title' => 'What is the first step to launch this app?',
        ]);

        if ($question instanceof QuizQuestion) {
            return;
        }

        $question = (new QuizQuestion())
            ->setQuiz($quiz)
            ->setTitle('What is the first step to launch this app?')
            ->setCategory($this->quizCategoryRepository->findOneBySlug('onboarding') ?? $this->quizCategoryRepository->findOneBySlug('general') ?? throw new \RuntimeException('Missing quiz categories fixtures.'))
            ->setLevel(QuizLevel::EASY)
            ->setPosition(1);

        $this->entityManager->persist($question);
    }
}
