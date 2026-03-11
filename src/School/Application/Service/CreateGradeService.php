<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Domain\Entity\Grade;
use App\School\Infrastructure\Repository\ExamRepository;
use App\School\Infrastructure\Repository\StudentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateGradeService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private ExamRepository $examRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(float $score, ?string $studentId, ?string $examId): Grade
    {
        $grade = (new Grade())->setScore($score);
        if (is_string($studentId)) {
            $grade->setStudent($this->studentRepository->find($studentId));
        }
        if (is_string($examId)) {
            $grade->setExam($this->examRepository->find($examId));
        }

        $this->entityManager->persist($grade);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_grade', $grade->getId()));

        return $grade;
    }
}
