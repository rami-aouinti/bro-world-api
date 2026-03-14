<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
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

    public function create(School $school, string $name, ?string $classId): Student
    {
        if (!is_string($classId)) {
            throw SchoolRelationException::unprocessable('classId is required');
        }

        $class = $this->classRepository->find($classId);
        if (!$class instanceof SchoolClass || $class->getSchool()?->getId() !== $school->getId()) {
            throw SchoolRelationException::notFound('classId');
        }

        $student = (new Student())->setName($name);
        $student->setSchoolClass($class);

        $this->entityManager->persist($student);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_student', $student->getId()));

        return $student;
    }
}
