<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Grade;

use App\School\Application\Service\CreateGradeService;
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
final readonly class CreateGradeController
{
    public function __construct(private CreateGradeService $createGradeService)
    {
    }

    #[Route('/v1/school/grades', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $grade = $this->createGradeService->create(
            (float)($payload['score'] ?? 0),
            is_string($payload['studentId'] ?? null) ? $payload['studentId'] : null,
            is_string($payload['examId'] ?? null) ? $payload['examId'] : null,
        );

        return new JsonResponse(['id' => $grade->getId()], JsonResponse::HTTP_CREATED);
    }
}
