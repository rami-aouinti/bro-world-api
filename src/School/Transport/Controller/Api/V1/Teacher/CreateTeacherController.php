<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Teacher;

use App\School\Application\Service\CreateTeacherService;
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
final readonly class CreateTeacherController
{
    public function __construct(private CreateTeacherService $createTeacherService)
    {
    }

    #[Route('/v1/school/teachers', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $teacher = $this->createTeacherService->create((string)($payload['name'] ?? ''));

        return new JsonResponse(['id' => $teacher->getId()], JsonResponse::HTTP_CREATED);
    }
}
