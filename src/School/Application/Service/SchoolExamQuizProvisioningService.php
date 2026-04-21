<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\Quiz\Domain\Entity\Quiz;
use App\School\Domain\Entity\Exam;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;

final readonly class SchoolExamQuizProvisioningService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Exam $exam): Quiz
    {
        if ($exam->getQuiz() instanceof Quiz) {
            return $exam->getQuiz();
        }

        $application = $exam->getSchoolClass()?->getSchool()?->getApplication();
        if (!$application instanceof Application) {
            throw new RuntimeException('Cannot provision exam quiz without application.');
        }
        $owner = $application->getUser();
        if (!$owner instanceof User) {
            throw new RuntimeException('Cannot provision exam quiz without application owner.');
        }

        $quiz = (new Quiz())
            ->setApplication($application)
            ->setOwner($owner)
            ->setTitle('Quiz: ' . $exam->getTitle())
            ->setDescription('Dedicated quiz provisioned for school exam ' . $exam->getId() . '.');

        $exam->setQuiz($quiz);

        $this->entityManager->persist($quiz);
        $this->entityManager->persist($exam);

        return $quiz;
    }
}
