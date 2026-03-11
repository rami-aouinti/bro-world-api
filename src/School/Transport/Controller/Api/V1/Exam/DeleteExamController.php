<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

use App\School\Application\Service\DeleteExamService;
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
final readonly class DeleteExamController
{
    public function __construct(private DeleteExamService $deleteExamService)
    {
    }

    #[Route('/v1/school/exams/{id}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $id): JsonResponse
    {
        if (!$this->deleteExamService->delete($id)) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
