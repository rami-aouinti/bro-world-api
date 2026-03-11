<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\ClassApplicationListService;
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
final readonly class ListClassesByApplicationController
{
    public function __construct(
        private ClassApplicationListService $classApplicationListService,
        private SchoolApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/school/{applicationSlug}/classes', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug);

        return new JsonResponse($this->classApplicationListService->getList($request, $applicationSlug, $school));
    }
}
