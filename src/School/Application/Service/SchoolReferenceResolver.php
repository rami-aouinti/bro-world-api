<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Exception\SchoolRelationException;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;
use App\School\Infrastructure\Repository\ExamRepository;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\School\Infrastructure\Repository\StudentRepository;
use App\School\Infrastructure\Repository\TeacherRepository;
use Ramsey\Uuid\Uuid;

final readonly class SchoolReferenceResolver
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private TeacherRepository $teacherRepository,
        private StudentRepository $studentRepository,
        private ExamRepository $examRepository,
    ) {
    }

    public function resolveClassInSchool(School $school, ?string $classId, string $reference = 'classId'): SchoolClass
    {
        if (!is_string($classId) || $classId === '') {
            throw SchoolRelationException::unprocessable($reference . ' is required');
        }

        $class = $this->classRepository->find($classId);
        if (!$class instanceof SchoolClass || $class->getSchool()?->getId() !== $school->getId()) {
            throw SchoolRelationException::notFound($reference);
        }

        return $class;
    }

    public function resolveTeacherInSchool(School $school, ?string $teacherId, string $reference = 'teacherId'): Teacher
    {
        if (!is_string($teacherId) || $teacherId === '') {
            throw SchoolRelationException::unprocessable($reference . ' is required');
        }

        $teacher = $this->teacherRepository->find($teacherId);
        if (!$teacher instanceof Teacher) {
            throw SchoolRelationException::notFound($reference);
        }

        foreach ($teacher->getClasses() as $teacherClass) {
            if ($teacherClass->getSchool()?->getId() === $school->getId()) {
                return $teacher;
            }
        }

        throw SchoolRelationException::notFound($reference);
    }

    public function resolveStudentInSchool(School $school, ?string $studentId, string $reference = 'studentId'): Student
    {
        if (!is_string($studentId) || $studentId === '') {
            throw SchoolRelationException::unprocessable($reference . ' is required');
        }

        $student = $this->studentRepository->find($studentId);
        if (!$student instanceof Student || $student->getSchoolClass()?->getSchool()?->getId() !== $school->getId()) {
            throw SchoolRelationException::notFound($reference);
        }

        return $student;
    }

    public function resolveExamInSchool(School $school, ?string $examId, string $reference = 'examId'): Exam
    {
        if (!is_string($examId) || $examId === '') {
            throw SchoolRelationException::unprocessable($reference . ' is required');
        }

        $exam = $this->examRepository->find($examId);
        if (!$exam instanceof Exam || $exam->getSchoolClass()?->getSchool()?->getId() !== $school->getId()) {
            throw SchoolRelationException::notFound($reference);
        }

        return $exam;
    }

    public function assertValidIdentifier(string $identifier, string $reference): void
    {
        if (!Uuid::isValid($identifier)) {
            throw SchoolRelationException::unprocessable($reference . ' has invalid format');
        }
    }
}
