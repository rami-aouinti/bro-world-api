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
    #[OA\Post(
        summary: 'Créer un étudiant',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'classId'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Alice Martin'),
                    new OA\Property(property: 'classId', type: 'string', format: 'uuid', example: '7600e750-f92f-4f9f-883a-26404b538f66'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 201, description: 'Étudiant créé.', content: new OA\JsonContent(example: ['id' => '4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0'])),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError'))]
    #[OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError'))]
    #[OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/SchoolValidationError'))]
    public function __invoke(string $applicationSlug, ?User $loggedInUser, Request $request): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        $payload = $request->toArray();

        $input = new CreateStudentInput();
        $input->name = (string)($payload['name'] ?? '');
        $input->classId = is_string($payload['classId'] ?? null) ? $payload['classId'] : '';

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $student = $this->createStudentService->create($school, $input->name, $input->classId);

        return new JsonResponse([
            'id' => $student->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
