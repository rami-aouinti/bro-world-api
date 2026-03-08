<?php

declare(strict_types=1);

namespace App\Quiz\Infrastructure\DataFixtures\ORM;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use Doctrine\Bundle\FixturesBundle\Fixture;
use App\Platform\Domain\Entity\Application;
use App\Quiz\Domain\Entity\Quiz;
use App\Quiz\Domain\Entity\QuizAnswer;
use App\Quiz\Domain\Entity\QuizQuestion;
use App\User\Domain\Entity\User;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadQuizData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $johnRoot = $this->getReference('User-john-root', User::class);
        $application = $this->getReference('Application-shop-ops-center', Application::class);

        $configuration = (new Configuration())
            ->setApplication($application)
            ->setConfigurationKey('quiz.module.configuration')
            ->setConfigurationValue(['shuffleQuestions' => true, 'timerSec' => 45])
            ->setScope(ConfigurationScope::PLATFORM)
            ->setPrivate(true);
        $manager->persist($configuration);

        $quiz = (new Quiz())->setApplication($application)->setOwner($johnRoot)->setConfiguration($configuration);
        $manager->persist($quiz);

        for ($i = 1; $i <= 8; ++$i) {
            $question = (new QuizQuestion())
                ->setQuiz($quiz)
                ->setTitle('Question fixture #' . $i)
                ->setLevel($i % 3 === 0 ? 'hard' : ($i % 2 === 0 ? 'medium' : 'easy'))
                ->setCategory($i % 2 === 0 ? 'backend' : 'frontend');
            $manager->persist($question);

            $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Right answer ' . $i)->setCorrect(true));
            $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Wrong answer A ' . $i)->setCorrect(false));
            $manager->persist((new QuizAnswer())->setQuestion($question)->setLabel('Wrong answer B ' . $i)->setCorrect(false));
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 42;
    }
}
