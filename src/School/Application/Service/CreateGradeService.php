<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\General\Application\Message\EntityCreated;
use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\School;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final readonly class CreateGradeService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    public function create(School $school, float $score, ?string $studentId, ?string $examId): Grade
    {
        $student = $this->referenceResolver->resolveStudentInSchool($school, $studentId);
        $exam = $this->referenceResolver->resolveExamInSchool($school, $examId);

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
