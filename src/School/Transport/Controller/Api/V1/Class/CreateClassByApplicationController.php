<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\CreateClassByApplicationService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Transport\Controller\Api\V1\Input\CreateClassByApplicationInput;
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
final readonly class CreateClassByApplicationController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateClassByApplicationService $createClassByApplicationService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/classes', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug);
        $payload = $request->toArray();

        $input = new CreateClassByApplicationInput();
        $input->name = (string)($payload['name'] ?? '');

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $class = $this->createClassByApplicationService->create($applicationSlug, $school, $input->name);

        return new JsonResponse([
            'id' => $class->getId(),
            'schoolId' => $school->getId(),
            'applicationSlug' => $applicationSlug,
        ], JsonResponse::HTTP_CREATED);
    }
}
