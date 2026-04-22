<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Teacher;

use App\School\Application\Service\DeleteTeacherService;
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
final readonly class DeleteTeacherController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private DeleteTeacherService $deleteTeacherService
    ) {
    }

    #[Route('/v1/school/teachers/{id}', methods: [Request::METHOD_DELETE], defaults: ['applicationSlug' => 'general'])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        if (!$this->deleteTeacherService->delete($id, $school)) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
