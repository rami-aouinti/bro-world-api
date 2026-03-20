<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\DataFixtures\ORM;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Plugin;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizAttempt;
use App\Quiz\Domain\Entity\QuizAttemptAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\Quiz\Domain\Enum\QuizCategory;
use App\Quiz\Domain\Enum\QuizLevel;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadQuizData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $users = [
            $this->getReference('User-john-root', User::class),
            $this->getReference('User-john-admin', User::class),
            $this->getReference('User-john-user', User::class),
        ];
        $quizPlugin = $this->getReference('Plugin-Quiz-Master', Plugin::class);

        $applications = $manager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicationPlugins', 'applicationPlugin')
            ->andWhere('applicationPlugin.plugin = :plugin OR application.slug = :generalSlug')
            ->setParameter('plugin', $quizPlugin)
            ->setParameter('generalSlug', 'general')
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        foreach ($applications as $applicationIndex => $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $isGeneralApplication = $application->getSlug() === 'general';

            $configuration = new Configuration()
                ->setApplication($application)
                ->setConfigurationKey('quiz.module.configuration')
                ->setConfigurationValue([
                    'shuffleQuestions' => true,
                    'timerSec' => 30 + ($applicationIndex * 15),
                    'showInstantCorrection' => $applicationIndex % 2 === 0,
                ])
                ->setScope(ConfigurationScope::PLATFORM)
                ->setPrivate(true);
            $manager->persist($configuration);

            $quiz = new Quiz()
                ->setApplication($application)
                ->setOwner($users[$applicationIndex % count($users)])
                ->setTitle($isGeneralApplication ? 'General user quiz' : sprintf('%s technical quiz', $application->getTitle()))
                ->setDescription($isGeneralApplication
                    ? 'General quiz entrypoint used by public and private endpoints.'
                    : 'Assess core application knowledge with progressive difficulty questions.')
                ->setPassScore(70)
                ->setPublished(true)
                ->setConfiguration($configuration);
            $manager->persist($quiz);
            $this->addReference('Quiz-' . $application->getSlug(), $quiz);

            if ($isGeneralApplication) {
                $this->addReference('Quiz-general', $quiz);
            }

            for ($questionIndex = 1; $questionIndex <= 12; $questionIndex++) {
                $question = (0 !== $questionIndex % 2) ? new QuizQuestion()
                    ->setQuiz($quiz)
                    ->setTitle($isGeneralApplication
                        ? 'General question fixture #' . $questionIndex
                        : 'Question fixture #' . $questionIndex . ' app #' . ($applicationIndex + 1))
                    ->setLevel($questionIndex % 3 === 0 ? QuizLevel::HARD : (QuizLevel::EASY))
                    ->setCategory($isGeneralApplication
                        ? QuizCategory::GENERAL
                        : ($questionIndex % 2 === 0 ? QuizCategory::BACKEND : QuizCategory::FRONTEND))
                    ->setPosition($questionIndex)
                    ->setPoints($questionIndex % 3 === 0 ? 3 : 1)
                    ->setExplanation('This explanation helps users understand the expected reasoning.') : new QuizQuestion()
                    ->setQuiz($quiz)
                    ->setTitle($isGeneralApplication
                        ? 'General question fixture #' . $questionIndex
                        : 'Question fixture #' . $questionIndex . ' app #' . ($applicationIndex + 1))
                    ->setLevel($questionIndex % 3 === 0 ? QuizLevel::HARD : (QuizLevel::MEDIUM))
                    ->setCategory($isGeneralApplication
                        ? QuizCategory::GENERAL
                        : ($questionIndex % 2 === 0 ? QuizCategory::BACKEND : QuizCategory::FRONTEND))
                    ->setPosition($questionIndex)
                    ->setPoints($questionIndex % 3 === 0 ? 3 : 1)
                    ->setExplanation('This explanation helps users understand the expected reasoning.');
                $manager->persist($question);
                $this->addReference(sprintf('QuizQuestion-%s-%d', $application->getSlug(), $questionIndex), $question);

                $correctAnswer = new QuizAnswer()
                    ->setQuestion($question)
                    ->setLabel('Right answer ' . $questionIndex)
                    ->setCorrect(true)
                    ->setPosition(1);
                $manager->persist($correctAnswer);
                $this->addReference(sprintf('QuizAnswer-%s-%d-correct', $application->getSlug(), $questionIndex), $correctAnswer);

                $wrongAnswerA = new QuizAnswer()
                    ->setQuestion($question)
                    ->setLabel('Wrong answer A ' . $questionIndex)
                    ->setCorrect(false)
                    ->setPosition(2);
                $manager->persist($wrongAnswerA);
                $this->addReference(sprintf('QuizAnswer-%s-%d-wrong-a', $application->getSlug(), $questionIndex), $wrongAnswerA);

                $wrongAnswerB = new QuizAnswer()
                    ->setQuestion($question)
                    ->setLabel('Wrong answer B ' . $questionIndex)
                    ->setCorrect(false)
                    ->setPosition(3);
                $manager->persist($wrongAnswerB);
                $this->addReference(sprintf('QuizAnswer-%s-%d-wrong-b', $application->getSlug(), $questionIndex), $wrongAnswerB);

                $wrongAnswerC = new QuizAnswer()
                    ->setQuestion($question)
                    ->setLabel('Wrong answer C ' . $questionIndex)
                    ->setCorrect(false)
                    ->setPosition(4);
                $manager->persist($wrongAnswerC);
                $this->addReference(sprintf('QuizAnswer-%s-%d-wrong-c', $application->getSlug(), $questionIndex), $wrongAnswerC);
            }

            foreach ($users as $userIndex => $user) {
                $attempt = new QuizAttempt()
                    ->setQuiz($quiz)
                    ->setUser($user)
                    ->setTotalQuestions(3)
                    ->setCorrectAnswers($userIndex % 2 === 0 ? 3 : 1)
                    ->setScore($userIndex % 2 === 0 ? 100.0 : 33.33)
                    ->setPassed($userIndex % 2 === 0);
                $manager->persist($attempt);
                $this->addReference(sprintf('QuizAttempt-%s-%d', $application->getSlug(), $userIndex + 1), $attempt);

                for ($attemptQuestionIndex = 1; $attemptQuestionIndex <= 3; $attemptQuestionIndex++) {
                    $question = $this->getReference(
                        sprintf('QuizQuestion-%s-%d', $application->getSlug(), $attemptQuestionIndex),
                        QuizQuestion::class
                    );
                    $answerReference = $userIndex % 2 === 0
                        ? sprintf('QuizAnswer-%s-%d-correct', $application->getSlug(), $attemptQuestionIndex)
                        : sprintf('QuizAnswer-%s-%d-wrong-a', $application->getSlug(), $attemptQuestionIndex);
                    $selectedAnswer = $this->getReference($answerReference, QuizAnswer::class);

                    $attemptAnswer = new QuizAttemptAnswer()
                        ->setAttempt($attempt)
                        ->setQuestion($question)
                        ->setSelectedAnswer($selectedAnswer)
                        ->setIsCorrect($selectedAnswer->isCorrect());
                    $manager->persist($attemptAnswer);
                }
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 42;
    }
}
