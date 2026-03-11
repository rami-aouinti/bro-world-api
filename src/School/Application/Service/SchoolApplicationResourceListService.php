<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Infrastructure\Repository\ExamRepository;
use App\School\Infrastructure\Repository\GradeRepository;
use App\School\Infrastructure\Repository\StudentRepository;
use App\School\Infrastructure\Repository\TeacherRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchoolApplicationResourceListService
{
    public function __construct(
        private StudentRepository $studentRepository,
        private TeacherRepository $teacherRepository,
        private ExamRepository $examRepository,
        private GradeRepository $gradeRepository,
        private SchoolViewMapper $viewMapper,
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function listByResource(string $resource, string $schoolId): array
    {
        return match ($resource) {
            'students' => $this->viewMapper->mapStudentCollection($this->studentRepository->createQueryBuilder('student')
                ->innerJoin('student.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->orderBy('student.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult()),
            'teachers' => $this->viewMapper->mapTeacherCollection($this->teacherRepository->createQueryBuilder('teacher')
                ->innerJoin('teacher.classes', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->orderBy('teacher.createdAt', 'DESC')
                ->distinct()
                ->setMaxResults(200)
                ->getQuery()
                ->getResult()),
            'exams' => $this->viewMapper->mapExamCollection($this->examRepository->createQueryBuilder('exam')
                ->innerJoin('exam.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->orderBy('exam.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult()),
            'grades' => $this->viewMapper->mapGradeCollection($this->gradeRepository->createQueryBuilder('grade')
                ->innerJoin('grade.exam', 'exam')
                ->innerJoin('exam.schoolClass', 'class')
                ->innerJoin('class.school', 'school')
                ->andWhere('school.id = :schoolId')
                ->setParameter('schoolId', $schoolId)
                ->orderBy('grade.createdAt', 'DESC')
                ->setMaxResults(200)
                ->getQuery()
                ->getResult()),
            default => throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource.'),
        };
    }
}
