<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Student;

use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Infrastructure\Repository\StudentRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListStudentsController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private StudentRepository $studentRepository,
        private SchoolViewMapper $viewMapper,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/students', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $items = $this->viewMapper->mapStudentCollection($this->studentRepository->createQueryBuilder('student')
            ->innerJoin('student.schoolClass', 'class')
            ->innerJoin('class.school', 'school')
            ->andWhere('school.id = :schoolId')
            ->setParameter('schoolId', $school->getId())
            ->orderBy('student.createdAt', 'DESC')
            ->setMaxResults(200)
            ->getQuery()
            ->getResult());

        return new JsonResponse($this->responseSerializer->list($items));
    }
}
