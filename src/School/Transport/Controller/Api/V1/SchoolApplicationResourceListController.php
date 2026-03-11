<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1;

use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Service\SchoolApplicationResourceListService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
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
final readonly class SchoolApplicationResourceListController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolApplicationResourceListService $resourceListService,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/{resource}', methods: [Request::METHOD_GET], requirements: [
        'resource' => 'students|teachers|exams|grades',
    ])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $resource): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug);
        $items = $this->resourceListService->listByResource($resource, $school->getId());

        return new JsonResponse($this->responseSerializer->list($items, null, [
            'applicationSlug' => $applicationSlug,
            'schoolId' => $school->getId(),
        ]));
    }
}
