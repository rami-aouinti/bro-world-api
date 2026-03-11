<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\School\Domain\Entity\Student;
use App\School\Infrastructure\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteStudentService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function delete(string $id): bool
    {
        $student = $this->studentRepository->find($id);
        if (!$student instanceof Student) {
            return false;
        }

        $this->entityManager->remove($student);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('school_student', $id));

        return true;
    }
}
