<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Infrastructure\Repository\SchoolClassRepository;
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
final readonly class ListClassesController
{
    public function __construct(
        private SchoolClassRepository $classRepository,
        private SchoolViewMapper $viewMapper,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    #[Route('/v1/school/{applicationSlug}/classes', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $items = $this->viewMapper->mapClassCollection($this->classRepository->findBy([], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse($this->responseSerializer->list($items));
    }
}
