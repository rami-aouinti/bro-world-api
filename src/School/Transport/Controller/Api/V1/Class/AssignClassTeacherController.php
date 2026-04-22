<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\ClassTeacherAssignmentService;
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
final readonly class AssignClassTeacherController
{
    public function __construct(
        private ClassTeacherAssignmentService $assignmentService,
        private SchoolApplicationScopeResolver $scopeResolver,
    ) {
    }
    #[Route('/v1/school/classes/{id}/teachers/{teacherId}', methods: [Request::METHOD_POST], defaults: ['applicationSlug' => 'general'])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id, string $teacherId, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $this->assignmentService->assign($school, $id, $teacherId);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
