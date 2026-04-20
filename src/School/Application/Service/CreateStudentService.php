<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\Student;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateStudentService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private UserRepository $userRepository,
    ) {
    }

    public function create(School $school, string $userId, ?string $classId): Student
    {
        $class = $this->referenceResolver->resolveClassInSchool($school, $classId);

        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            throw SchoolRelationException::notFound('userId');
        }

        $student = (new Student())->setUser($user);
        $student->setSchoolClass($class);

        $this->entityManager->persist($student);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_student', $student->getId()));

        return $student;
    }
}
