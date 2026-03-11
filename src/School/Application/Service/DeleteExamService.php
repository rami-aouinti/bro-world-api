<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\School\Domain\Entity\Exam;
use App\School\Infrastructure\Repository\ExamRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class DeleteExamService
{
    public function __construct(
        private ExamRepository $examRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function delete(string $id): bool
    {
        $exam = $this->examRepository->find($id);
        if (!$exam instanceof Exam) {
            return false;
        }

        $this->entityManager->remove($exam);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('school_exam', $id));

        return true;
    }
}
