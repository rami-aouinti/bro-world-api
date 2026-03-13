<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\Student;
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
        if (!is_string($studentId)) {
            throw SchoolRelationException::unprocessable('studentId is required');
        }

        if (!is_string($examId)) {
            throw SchoolRelationException::unprocessable('examId is required');
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student instanceof Student) {
            throw SchoolRelationException::notFound('studentId');
        }

        $exam = $this->examRepository->find($examId);
        if (!$exam instanceof Exam) {
            throw SchoolRelationException::notFound('examId');
        }

        if ($student->getSchoolClass()?->getId() !== $exam->getSchoolClass()?->getId()) {
            throw SchoolRelationException::unprocessable('studentId and examId must belong to the same class');
        }

        $grade = (new Grade())->setScore($score);
        $grade->setStudent($student);
        $grade->setExam($exam);

        $this->entityManager->persist($grade);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('school_grade', $grade->getId()));

        return $grade;
    }
}
