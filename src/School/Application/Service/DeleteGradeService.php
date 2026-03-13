<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\GradeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteGradeService
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private SchoolResourceAccessService $resourceAccessService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function delete(string $id, School $school): bool
    {
        $grade = $this->gradeRepository->find($id);
        if (!$grade instanceof Grade || !$this->resourceAccessService->belongsToSchool($grade, $school)) {
            return false;
        }

        $this->entityManager->remove($grade);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('school_grade', $id));

        return true;
    }
}
