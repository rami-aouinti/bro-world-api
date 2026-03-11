<?php

declare(strict_types=1);

namespace App\School\Application\Serializer;

use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;

final readonly class SchoolViewMapper
{
    /** @param array<int,SchoolClass> $classes
     * @return array<int,array<string,mixed>>
     */
    public function mapClassCollection(array $classes): array
    {
        $items = [];
        foreach ($classes as $class) {
            $items[] = $this->mapClass($class);
        }

        return $items;
    }

    /** @return array<string,mixed> */
    public function mapClass(SchoolClass $class): array
    {
        return [
            'id' => $class->getId(),
            'name' => $class->getName(),
            'schoolId' => $class->getSchool()?->getId(),
        ];
    }

    /** @param array<int,Student> $students
     * @return array<int,array<string,mixed>>
     */
    public function mapStudentCollection(array $students): array
    {
        $items = [];
        foreach ($students as $student) {
            $items[] = [
                'id' => $student->getId(),
                'name' => $student->getName(),
                'classId' => $student->getSchoolClass()?->getId(),
            ];
        }

        return $items;
    }

    /** @param array<int,Teacher> $teachers
     * @return array<int,array<string,mixed>>
     */
    public function mapTeacherCollection(array $teachers): array
    {
        $items = [];
        foreach ($teachers as $teacher) {
            $items[] = [
                'id' => $teacher->getId(),
                'name' => $teacher->getName(),
            ];
        }

        return $items;
    }

    /** @param array<int,Exam> $exams
     * @return array<int,array<string,mixed>>
     */
    public function mapExamCollection(array $exams): array
    {
        $items = [];
        foreach ($exams as $exam) {
            $items[] = [
                'id' => $exam->getId(),
                'title' => $exam->getTitle(),
                'classId' => $exam->getSchoolClass()?->getId(),
                'className' => $exam->getSchoolClass()?->getName(),
                'teacherId' => $exam->getTeacher()?->getId(),
                'teacherName' => $exam->getTeacher()?->getName(),
                'updatedAt' => $exam->getUpdatedAt()?->format(DATE_ATOM),
            ];
        }

        return $items;
    }

    /** @param array<int,Grade> $grades
     * @return array<int,array<string,mixed>>
     */
    public function mapGradeCollection(array $grades): array
    {
        $items = [];
        foreach ($grades as $grade) {
            $items[] = [
                'id' => $grade->getId(),
                'score' => $grade->getScore(),
                'studentId' => $grade->getStudent()?->getId(),
                'examId' => $grade->getExam()?->getId(),
            ];
        }

        return $items;
    }
}
