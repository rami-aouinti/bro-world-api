<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\CreateClassService;
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
final readonly class CreateClassController
{
    public function __construct(private CreateClassService $createClassService)
    {
    }

    #[Route('/v1/school/classes', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = (array)json_decode((string)$request->getContent(), true);
        $class = $this->createClassService->create((string)($payload['name'] ?? ''), is_string($payload['schoolId'] ?? null) ? $payload['schoolId'] : null);

        return new JsonResponse(['id' => $class->getId()], JsonResponse::HTTP_CREATED);
    }
}
