<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Teacher;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\School\Infrastructure\Repository\TeacherRepository;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ClassTeacherAssignmentService
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private TeacherRepository $teacherRepository,
    ) {
    }

    public function assign(string $classId, string $teacherId): void
    {
        [$class, $teacher] = $this->resolve($classId, $teacherId);

        if (!$teacher->getClasses()->contains($class)) {
            $teacher->getClasses()->add($class);
            $this->teacherRepository->save($teacher);
        }
    }

    public function unassign(string $classId, string $teacherId): void
    {
        [$class, $teacher] = $this->resolve($classId, $teacherId);

        if ($teacher->getClasses()->contains($class)) {
            $teacher->getClasses()->removeElement($class);
            $this->teacherRepository->save($teacher);
        }
    }

    /** @return array{0:SchoolClass,1:Teacher} */
    private function resolve(string $classId, string $teacherId): array
    {
        if (!Uuid::isValid($classId) || !Uuid::isValid($teacherId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid identifier format.');
        }

        $class = $this->classRepository->find($classId);
        if (!$class instanceof SchoolClass) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Class not found.');
        }

        $teacher = $this->teacherRepository->find($teacherId);
        if (!$teacher instanceof Teacher) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Teacher not found.');
        }

        return [$class, $teacher];
    }
}
