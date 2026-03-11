<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

use App\School\Application\Service\CreateExamService;
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
final readonly class CreateExamController
{
    public function __construct(private CreateExamService $createExamService)
    {
    }

    #[Route('/v1/school/exams', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $exam = $this->createExamService->create(
            (string)($payload['title'] ?? ''),
            is_string($payload['classId'] ?? null) ? $payload['classId'] : null,
            is_string($payload['teacherId'] ?? null) ? $payload['teacherId'] : null,
        );

        return new JsonResponse(['id' => $exam->getId()], JsonResponse::HTTP_CREATED);
    }
}
