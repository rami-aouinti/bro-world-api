<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Student;

use App\School\Application\Service\CreateStudentService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Transport\Controller\Api\V1\Input\CreateStudentInput;
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
final readonly class CreateStudentController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateStudentService $createStudentService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[Route('/v1/school/applications/{applicationSlug}/students', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, ?User $loggedInUser, Request $request): JsonResponse
    {
        $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        $payload = $request->toArray();

        $input = new CreateStudentInput();
        $input->name = (string)($payload['name'] ?? '');
        $input->classId = is_string($payload['classId'] ?? null) ? $payload['classId'] : '';

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $student = $this->createStudentService->create($input->name, $input->classId);

        return new JsonResponse([
            'id' => $student->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
