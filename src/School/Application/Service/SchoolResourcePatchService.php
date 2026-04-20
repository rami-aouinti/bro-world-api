<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use App\School\Infrastructure\Repository\CourseRepository;
use App\School\Infrastructure\Repository\ExamRepository;
use App\School\Infrastructure\Repository\GradeRepository;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\School\Infrastructure\Repository\StudentRepository;
use App\School\Infrastructure\Repository\TeacherRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchoolResourcePatchService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private StudentRepository $studentRepository,
        private TeacherRepository $teacherRepository,
        private CourseRepository $courseRepository,
        private UserRepository $userRepository,
        private ExamRepository $examRepository,
        private GradeRepository $gradeRepository,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function patch(SchoolClass|Student|Teacher|Exam|Grade $entity, string $resource, array $payload): void
    {
        match ($resource) {
            'classes' => $this->patchClass($entity, $payload),
            'students' => $this->patchStudent($entity, $payload),
            'teachers' => $this->patchTeacher($entity, $payload),
            'exams' => $this->patchExam($entity, $payload),
            'grades' => $this->patchGrade($entity, $payload),
            default => throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource.'),
        };
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function patchClass(SchoolClass|Student|Teacher|Exam|Grade $entity, array $payload): void
    {
        if (!$entity instanceof SchoolClass) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource payload.');
        }

        if (array_key_exists('name', $payload)) {
            $name = trim((string)$payload['name']);
            if ($name === '') {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "name" cannot be blank.');
            }
            $entity->setName($name);
        }

        $this->classRepository->save($entity);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function patchStudent(SchoolClass|Student|Teacher|Exam|Grade $entity, array $payload): void
    {
        if (!$entity instanceof Student) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource payload.');
        }

        if (array_key_exists('userId', $payload)) {
            $entity->setUser($this->resolveUser((string)$payload['userId']));
        }

        if (array_key_exists('classId', $payload)) {
            $classId = (string)$payload['classId'];
            if (!Uuid::isValid($classId)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "classId" must be a valid UUID.');
            }
            $schoolClass = $this->classRepository->find($classId);
            if (!$schoolClass instanceof SchoolClass) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Class not found.');
            }
            $entity->setSchoolClass($schoolClass);
        }

        $this->studentRepository->save($entity);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function patchTeacher(SchoolClass|Student|Teacher|Exam|Grade $entity, array $payload): void
    {
        if (!$entity instanceof Teacher) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource payload.');
        }

        if (array_key_exists('userId', $payload)) {
            $entity->setUser($this->resolveUser((string)$payload['userId']));
        }

        $this->teacherRepository->save($entity);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function patchExam(SchoolClass|Student|Teacher|Exam|Grade $entity, array $payload): void
    {
        if (!$entity instanceof Exam) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource payload.');
        }

        if (array_key_exists('title', $payload)) {
            $title = trim((string)$payload['title']);
            if ($title === '') {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "title" cannot be blank.');
            }
            $entity->setTitle($title);
        }

        if (array_key_exists('classId', $payload)) {
            $classId = (string)$payload['classId'];
            if (!Uuid::isValid($classId)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "classId" must be a valid UUID.');
            }
            $class = $this->classRepository->find($classId);
            if (!$class instanceof SchoolClass) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Class not found.');
            }
            $entity->setSchoolClass($class);
        }

        if (array_key_exists('courseId', $payload)) {
            $courseId = (string)$payload['courseId'];
            if (!Uuid::isValid($courseId)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "courseId" must be a valid UUID.');
            }
            $course = $this->courseRepository->find($courseId);
            if (!$course instanceof Course) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Course not found.');
            }
            $entity->setCourse($course);
        }

        if (array_key_exists('teacherId', $payload)) {
            $teacherId = (string)$payload['teacherId'];
            if (!Uuid::isValid($teacherId)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "teacherId" must be a valid UUID.');
            }
            $teacher = $this->teacherRepository->find($teacherId);
            if (!$teacher instanceof Teacher) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Teacher not found.');
            }
            $entity->setTeacher($teacher);
        }

        if (array_key_exists('type', $payload)) {
            $type = ExamType::tryFrom((string)$payload['type']);
            if ($type === null) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Invalid exam type.');
            }
            $entity->setType($type);
        }

        if (array_key_exists('status', $payload)) {
            $status = ExamStatus::tryFrom((string)$payload['status']);
            if ($status === null) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Invalid exam status.');
            }
            $entity->setStatus($status);
        }

        if (array_key_exists('term', $payload)) {
            $term = Term::tryFrom((string)$payload['term']);
            if ($term === null) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Invalid exam term.');
            }
            $entity->setTerm($term);
        }

        $this->examRepository->save($entity);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function patchGrade(SchoolClass|Student|Teacher|Exam|Grade $entity, array $payload): void
    {
        if (!$entity instanceof Grade) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid resource payload.');
        }

        if (array_key_exists('courseId', $payload)) {
            $courseId = (string)$payload['courseId'];
            if (!Uuid::isValid($courseId)) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "courseId" must be a valid UUID.');
            }
            $course = $this->courseRepository->find($courseId);
            if (!$course instanceof Course) {
                throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Course not found.');
            }
            $entity->setCourse($course);
        }

        if (array_key_exists('score', $payload)) {
            if (!is_numeric($payload['score'])) {
                throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "score" must be numeric.');
            }
            $entity->setScore((float)$payload['score']);
        }

        $this->gradeRepository->save($entity);
    }

    private function resolveUser(string $userId): User
    {
        if (!Uuid::isValid($userId)) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Field "userId" must be a valid UUID.');
        }

        $user = $this->userRepository->find($userId);
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'User not found.');
        }

        return $user;
    }
}
