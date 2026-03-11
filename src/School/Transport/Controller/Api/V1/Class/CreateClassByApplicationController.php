<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\CreateClassByApplicationService;
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
final readonly class CreateClassByApplicationController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateClassByApplicationService $createClassByApplicationService,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/classes', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $class = $this->createClassByApplicationService->create($applicationSlug, $school, (string)($payload['name'] ?? ''));

        return new JsonResponse([
            'id' => $class->getId(),
            'schoolId' => $school->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
