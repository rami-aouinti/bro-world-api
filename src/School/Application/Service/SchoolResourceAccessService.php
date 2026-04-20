<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\School;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;
use App\School\Infrastructure\Repository\CourseRepository;
use App\School\Infrastructure\Repository\ExamRepository;
use App\School\Infrastructure\Repository\GradeRepository;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\School\Infrastructure\Repository\StudentRepository;
use App\School\Infrastructure\Repository\TeacherRepository;

final readonly class SchoolResourceAccessService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private StudentRepository $studentRepository,
        private TeacherRepository $teacherRepository,
        private CourseRepository $courseRepository,
        private ExamRepository $examRepository,
        private GradeRepository $gradeRepository,
    ) {
    }

    public function find(string $resource, string $id): SchoolClass|Student|Teacher|Course|Exam|Grade|null
    {
        return match ($resource) {
            'classes' => $this->classRepository->find($id),
            'students' => $this->studentRepository->find($id),
            'teachers' => $this->teacherRepository->find($id),
            'courses' => $this->courseRepository->find($id),
            'exams' => $this->examRepository->find($id),
            'grades' => $this->gradeRepository->find($id),
            default => null,
        };
    }

    public function belongsToSchool(SchoolClass|Student|Teacher|Course|Exam|Grade $entity, School $school): bool
    {
        if ($entity instanceof SchoolClass) {
            return $entity->getSchool()?->getId() === $school->getId();
        }

        if ($entity instanceof Student) {
            return $entity->getSchoolClass()?->getSchool()?->getId() === $school->getId();
        }

        if ($entity instanceof Exam) {
            return $entity->getSchoolClass()?->getSchool()?->getId() === $school->getId();
        }

        if ($entity instanceof Course) {
            return $entity->getSchoolClass()?->getSchool()?->getId() === $school->getId();
        }

        if ($entity instanceof Grade) {
            return $entity->getExam()?->getSchoolClass()?->getSchool()?->getId() === $school->getId();
        }

        foreach ($entity->getClasses() as $class) {
            if ($class->getSchool()?->getId() === $school->getId()) {
                return true;
            }
        }

        return false;
    }
}
