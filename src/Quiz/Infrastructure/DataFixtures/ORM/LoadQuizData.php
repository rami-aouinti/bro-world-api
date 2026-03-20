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
use App\Quiz\Domain\Entity\QuizCategory;
use App\Quiz\Domain\Entity\QuizQuestion;
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

        $categoryFixtures = [
            ['general', 'General Knowledge', '#6366F1'],
            ['backend', 'Backend', '#0EA5E9'],
            ['frontend', 'Frontend', '#EC4899'],
            ['devops', 'DevOps', '#14B8A6'],
            ['onboarding', 'Onboarding', '#8B5CF6'],
            ['data', 'Data', '#06B6D4'],
            ['security', 'Security', '#EF4444'],
            ['architecture', 'Architecture', '#F97316'],
            ['mobile', 'Mobile', '#84CC16'],
            ['testing', 'Testing', '#64748B'],
        ];

        foreach ($categoryFixtures as $position => [$slug, $name, $color]) {
            $category = (new QuizCategory())
                ->setSlug($slug)
                ->setName($name)
                ->setPosition($position + 1)
                ->setColor($color)
                ->setIsActive(true);
            $manager->persist($category);
            $this->addReference('QuizCategory-' . $slug, $category);
        }

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

            for ($questionIndex = 1; $questionIndex <= 20; $questionIndex++) {
                $level = match ($questionIndex % 3) {
                    0 => QuizLevel::HARD,
                    1 => QuizLevel::EASY,
                    default => QuizLevel::MEDIUM,
                };
                $categorySlug = $isGeneralApplication
                    ? $categoryFixtures[($questionIndex - 1) % count($categoryFixtures)][0]
                    : ($questionIndex % 2 === 0 ? 'backend' : 'frontend');
                $category = $this->getReference('QuizCategory-' . $categorySlug, QuizCategory::class);

                $question = (new QuizQuestion())
                    ->setQuiz($quiz)
                    ->setTitle($isGeneralApplication
                        ? 'General question fixture #' . $questionIndex
                        : 'Question fixture #' . $questionIndex . ' app #' . ($applicationIndex + 1))
                    ->setLevel($level)
                    ->setCategory($category)
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

                foreach (['A', 'B', 'C'] as $idx => $label) {
                    $wrongAnswer = (new QuizAnswer())
                        ->setQuestion($question)
                        ->setLabel('Wrong answer ' . $label . ' ' . $questionIndex)
                        ->setCorrect(false)
                        ->setPosition($idx + 2);
                    $manager->persist($wrongAnswer);
                    $this->addReference(sprintf('QuizAnswer-%s-%d-wrong-%s', $application->getSlug(), $questionIndex, strtolower($label)), $wrongAnswer);
                }
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
