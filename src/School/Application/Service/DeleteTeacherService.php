<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\Teacher;
use App\School\Infrastructure\Repository\TeacherRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteTeacherService
{
    public function __construct(
        private TeacherRepository $teacherRepository,
        private SchoolResourceAccessService $resourceAccessService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function delete(string $id, School $school): bool
    {
        $teacher = $this->teacherRepository->find($id);
        if (!$teacher instanceof Teacher || !$this->resourceAccessService->belongsToSchool($teacher, $school)) {
            return false;
        }

        $this->entityManager->remove($teacher);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('school_teacher', $id));

        return true;
    }
}
