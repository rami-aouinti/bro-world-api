<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\Student;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateStudentService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(string $name, ?string $classId): Student
    {
        $student = (new Student())->setName($name);
        if (is_string($classId)) {
            $student->setSchoolClass($this->classRepository->find($classId));
        }

        $this->entityManager->persist($student);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_student', $student->getId()));

        return $student;
    }
}
