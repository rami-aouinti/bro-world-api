<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Student;

use App\School\Application\Service\DeleteStudentService;
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
final readonly class DeleteStudentController
{
    public function __construct(
        private DeleteStudentService $deleteStudentService
    ) {
    }

    #[Route('/v1/school/{applicationSlug}/students/{id}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        if (!$this->deleteStudentService->delete($id)) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
