<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Teacher;

use App\School\Application\Service\CreateTeacherService;
use App\School\Transport\Controller\Api\V1\Input\CreateTeacherInput;
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
final readonly class CreateTeacherController
{
    public function __construct(
        private CreateTeacherService $createTeacherService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/{applicationSlug}/teachers', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = $request->toArray();

        $input = new CreateTeacherInput();
        $input->name = (string)($payload['name'] ?? '');

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $teacher = $this->createTeacherService->create($input->name);

        return new JsonResponse([
            'id' => $teacher->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
