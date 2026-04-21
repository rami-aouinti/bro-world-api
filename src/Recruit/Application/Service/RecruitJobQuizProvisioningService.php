<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\Platform\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Job;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final readonly class RecruitJobQuizProvisioningService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Job $job): Quiz
    {
        if ($job->getQuiz() instanceof Quiz) {
            return $job->getQuiz();
        }

        $application = $job->getRecruit()?->getApplication();
        if (!$application instanceof Application) {
            throw new RuntimeException('Cannot provision job quiz without application.');
        }
        $owner = $application->getUser();
        if (!$owner instanceof User) {
            throw new RuntimeException('Cannot provision job quiz without application owner.');
        }

        $quiz = (new Quiz())
            ->setApplication($application)
            ->setOwner($owner)
            ->setTitle('Quiz: ' . $job->getTitle())
            ->setDescription('Dedicated quiz provisioned for recruit job ' . $job->getId() . '.');

        $job->setQuiz($quiz);

        $this->entityManager->persist($quiz);
        $this->entityManager->persist($job);

        return $quiz;
    }
}
