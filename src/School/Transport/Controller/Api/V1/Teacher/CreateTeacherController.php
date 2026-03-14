<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Teacher;

use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Application\Service\CreateTeacherService;
use App\School\Transport\Controller\Api\V1\Input\CreateTeacherInput;
use App\School\Transport\Controller\Api\V1\Input\SchoolInputValidator;
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
final readonly class CreateTeacherController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateTeacherService $createTeacherService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/teachers', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError'))]
    #[OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/SchoolValidationError'))]
    public function __invoke(string $applicationSlug, ?User $loggedInUser, Request $request): JsonResponse
    {
        $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

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
