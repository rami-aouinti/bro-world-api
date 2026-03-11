<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\CreateClassService;
use App\School\Transport\Controller\Api\V1\Input\CreateClassInput;
use App\School\Transport\Controller\Api\V1\Input\SchoolInputValidator;
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
    public function __construct(
        private CreateClassService $createClassService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/classes', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = $request->toArray();

        $input = new CreateClassInput();
        $input->name = (string)($payload['name'] ?? '');
        $input->schoolId = is_string($payload['schoolId'] ?? null) ? $payload['schoolId'] : '';

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $class = $this->createClassService->create($input->name, $input->schoolId);

        return new JsonResponse([
            'id' => $class->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
