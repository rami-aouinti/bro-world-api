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

    /**
     * @return array<string,mixed>
     */
    public function mapClass(SchoolClass $class): array
    {
        return [
            'id' => $class->getId(),
            'name' => $class->getName(),
            'schoolId' => $class->getSchool()?->getId(),
            'schoolName' => $class->getSchool()?->getName(),
        ];
    }

    /** @param array<int,Student> $students
     * @return array<int,array<string,mixed>>
     */
    public function mapStudentCollection(array $students): array
    {
        $items = [];
        foreach ($students as $student) {
            $items[] = $this->mapStudent($student);
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function mapStudent(Student $student): array
    {
        return [
            'id' => $student->getId(),
            'name' => $student->getDisplayName(),
            'photo' => $student->getUser()?->getPhoto(),
            'userId' => $student->getUser()?->getId(),
            'classId' => $student->getSchoolClass()?->getId(),
            'className' => $student->getSchoolClass()?->getName(),
        ];
    }

    /** @param array<int,Teacher> $teachers
     * @return array<int,array<string,mixed>>
     */
    public function mapTeacherCollection(array $teachers): array
    {
        $items = [];
        foreach ($teachers as $teacher) {
            $items[] = $this->mapTeacher($teacher);
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function mapTeacher(Teacher $teacher): array
    {
        return [
            'id' => $teacher->getId(),
            'name' => $teacher->getDisplayName(),
            'userId' => $teacher->getUser()?->getId(),
            'photo' => $teacher->getUser()?->getPhoto(),
        ];
    }

    /** @param array<int,Exam> $exams
     * @return array<int,array<string,mixed>>
     */
    public function mapExamCollection(array $exams): array
    {
        $items = [];
        foreach ($exams as $exam) {
            $items[] = $this->mapExam($exam);
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function mapExam(Exam $exam): array
    {
        return [
            'id' => $exam->getId(),
            'title' => $exam->getTitle(),
            'classId' => $exam->getSchoolClass()?->getId(),
            'className' => $exam->getSchoolClass()?->getName(),
            'teacherId' => $exam->getTeacher()?->getId(),
            'teacherName' => $exam->getTeacher()?->getDisplayName(),
            'courseId' => $exam->getCourse()?->getId(),
            'courseName' => $exam->getCourse()?->getName(),
            'type' => $exam->getType()->value,
            'status' => $exam->getStatus()->value,
            'term' => $exam->getTerm()->value,
            'updatedAt' => $exam->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /** @param array<int,Grade> $grades
     * @return array<int,array<string,mixed>>
     */
    public function mapGradeCollection(array $grades): array
    {
        $items = [];
        foreach ($grades as $grade) {
            $items[] = $this->mapGrade($grade);
        }

        return $items;
    }

    /**
     * @return array<string,mixed>
     */
    public function mapGrade(Grade $grade): array
    {
        return [
            'id' => $grade->getId(),
            'score' => $grade->getScore(),
            'studentId' => $grade->getStudent()?->getId(),
            'student' => [
                'id' => $grade->getStudent()?->getId(),
                'name' => $grade->getStudent()?->getDisplayName(),
                'photo' => $grade->getStudent()?->getUser()?->getPhoto(),
            ],
            'examId' => $grade->getExam()?->getId(),
            'examName' => $grade->getExam()?->getTitle(),
            'courseId' => $grade->getCourse()?->getId(),
            'courseName' => $grade->getCourse()?->getName(),
        ];
    }
}
