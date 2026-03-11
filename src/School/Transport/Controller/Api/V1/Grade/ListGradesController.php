<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Grade;

use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Infrastructure\Repository\GradeRepository;
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
final readonly class ListGradesController
{
    public function __construct(
        private GradeRepository $gradeRepository,
        private SchoolViewMapper $viewMapper,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    #[Route('/v1/school/{applicationSlug}/grades', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = $this->viewMapper->mapGradeCollection($this->gradeRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse($this->responseSerializer->list($items));
    }
}
