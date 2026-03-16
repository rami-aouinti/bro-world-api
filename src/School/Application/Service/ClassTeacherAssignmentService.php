<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\TeacherRepository;

final readonly class ClassTeacherAssignmentService
{
    public function __construct(
        private SchoolReferenceResolver $referenceResolver,
        private TeacherRepository $teacherRepository,
    ) {
    }

    public function assign(School $school, string $classId, string $teacherId): void
    {
        [$class, $teacher] = $this->resolve($school, $classId, $teacherId);

        if (!$teacher->getClasses()->contains($class)) {
            $teacher->getClasses()->add($class);
            $this->teacherRepository->save($teacher);
        }
    }

    public function unassign(School $school, string $classId, string $teacherId): void
    {
        [$class, $teacher] = $this->resolve($school, $classId, $teacherId);

        if ($teacher->getClasses()->contains($class)) {
            $teacher->getClasses()->removeElement($class);
            $this->teacherRepository->save($teacher);
        }
    }

    /**
     * @return array{0:\App\School\Domain\Entity\SchoolClass,1:\App\School\Domain\Entity\Teacher}
     */
    private function resolve(School $school, string $classId, string $teacherId): array
    {
        $this->referenceResolver->assertValidIdentifier($classId, 'classId');
        $this->referenceResolver->assertValidIdentifier($teacherId, 'teacherId');

        return [
            $this->referenceResolver->resolveClassInSchool($school, $classId),
            $this->referenceResolver->resolveTeacherInSchool($school, $teacherId),
        ];
    }
}
