<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\Student;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateStudentService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(School $school, string $name, ?string $classId): Student
    {
        $class = $this->referenceResolver->resolveClassInSchool($school, $classId);

        $student = (new Student())->setName($name);
        $student->setSchoolClass($class);

        $this->entityManager->persist($student);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_student', $student->getId()));

        return $student;
    }
}
