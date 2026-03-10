<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\DataFixtures\ORM;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\Platform\Domain\Entity\Application;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
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

        $applications = [
            $this->getReference('Application-shop-ops-center', Application::class),
            $this->getReference('Application-crm-sales-hub', Application::class),
            $this->getReference('Application-school-campus-core', Application::class),
        ];

        foreach ($applications as $applicationIndex => $application) {
            $configuration = (new Configuration())
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

            $quiz = (new Quiz())
                ->setApplication($application)
                ->setOwner($users[$applicationIndex % count($users)])
                ->setConfiguration($configuration);
            $manager->persist($quiz);

            for ($questionIndex = 1; $questionIndex <= 12; $questionIndex++) {
                $question = (new QuizQuestion())
                    ->setQuiz($quiz)
                    ->setTitle('Question fixture #' . $questionIndex . ' app #' . ($applicationIndex + 1))
                    ->setLevel($questionIndex % 3 === 0 ? 'hard' : ($questionIndex % 2 === 0 ? 'medium' : 'easy'))
                    ->setCategory($questionIndex % 2 === 0 ? 'backend' : 'frontend');
                $manager->persist($question);

                $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Right answer ' . $questionIndex)->setCorrect(true));
                $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Wrong answer A ' . $questionIndex)->setCorrect(false));
                $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Wrong answer B ' . $questionIndex)->setCorrect(false));
                $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Wrong answer C ' . $questionIndex)->setCorrect(false));
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
