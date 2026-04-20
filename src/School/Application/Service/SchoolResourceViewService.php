<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Domain\Entity\Exam;
use App\School\Domain\Entity\Grade;
use App\School\Domain\Entity\Course;
use App\School\Domain\Entity\SchoolClass;
use App\School\Domain\Entity\Student;
use App\School\Domain\Entity\Teacher;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchoolResourceViewService
{
    public function __construct(
        private SchoolResourceAccessService $resourceAccessService,
        private SchoolViewMapper $viewMapper,
    ) {
    }

    public function findOr404(string $resource, string $id): SchoolClass|Student|Teacher|Course|Exam|Grade
    {
        if (!Uuid::isValid($id)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid identifier format.');
        }

        $entity = $this->resourceAccessService->find($resource, $id);
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found.');
        }

        return $entity;
    }

    /**
     * @return array<string,mixed>
     */
    public function map(SchoolClass|Student|Teacher|Course|Exam|Grade $entity): array
    {
        return match (true) {
            $entity instanceof SchoolClass => $this->viewMapper->mapClass($entity),
            $entity instanceof Student => $this->viewMapper->mapStudent($entity),
            $entity instanceof Teacher => $this->viewMapper->mapTeacher($entity),
            $entity instanceof Course => $this->viewMapper->mapCourse($entity),
            $entity instanceof Exam => $this->viewMapper->mapExam($entity),
            $entity instanceof Grade => $this->viewMapper->mapGrade($entity),
        };
    }
}
