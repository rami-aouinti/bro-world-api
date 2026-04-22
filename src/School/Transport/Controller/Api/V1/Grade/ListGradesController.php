<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Grade;

use App\School\Application\Service\ListGradesService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
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
final readonly class ListGradesController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private ListGradesService $listGradesService,
    ) {
    }

    #[Route('/v1/school/grades', methods: [Request::METHOD_GET], defaults: ['applicationSlug' => 'general'])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        return new JsonResponse($this->listGradesService->list($request, $school));
    }
}
