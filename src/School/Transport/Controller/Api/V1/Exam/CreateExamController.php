<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

use App\School\Application\Service\CreateExamService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use App\School\Transport\Controller\Api\V1\Input\CreateExamInput;
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
final readonly class CreateExamController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateExamService $createExamService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[OA\Post(
        path: '/v1/school/applications/{applicationSlug}/exams',
        summary: 'Créer un examen',
        tags: ['School'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'classId', 'courseId', 'teacherId', 'type', 'status', 'term'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Examen Mathematiques - Trimestre 1'),
                new OA\Property(property: 'classId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'courseId', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'teacherId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'type', type: 'string', enum: ['QUIZ', 'MIDTERM', 'FINAL', 'ORAL'], example: 'QUIZ'),
                new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PUBLISHED', 'CLOSED'], example: 'DRAFT'),
                new OA\Property(property: 'term', type: 'string', enum: ['TERM_1', 'TERM_2', 'TERM_3'], example: 'TERM_1'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Examen créé'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/SchoolValidationError')),
        ],
    )]
    #[Route('/v1/school/applications/{applicationSlug}/exams', methods: [Request::METHOD_POST], defaults: ['applicationSlug' => 'general'])]
    #[Route('/v1/school/general/exams', methods: [Request::METHOD_POST], defaults: ['applicationSlug' => 'general'])]
    #[Route('/v1/school/exams', methods: [Request::METHOD_POST], defaults: ['applicationSlug' => 'general'])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, ?User $loggedInUser, Request $request): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        $payload = $request->toArray();

        $input = new CreateExamInput();
        $input->title = (string)($payload['title'] ?? '');
        $input->classId = is_string($payload['classId'] ?? null) ? $payload['classId'] : '';
        $input->courseId = is_string($payload['courseId'] ?? null) ? $payload['courseId'] : '';
        $input->teacherId = is_string($payload['teacherId'] ?? null) ? $payload['teacherId'] : '';
        $input->type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $input->status = is_string($payload['status'] ?? null) ? $payload['status'] : '';
        $input->term = is_string($payload['term'] ?? null) ? $payload['term'] : '';

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $exam = $this->createExamService->create(
            $school,
            $input->title,
            $input->classId,
            $input->courseId,
            $input->teacherId,
            ExamType::from($input->type),
            ExamStatus::from($input->status),
            Term::from($input->term),
        );

        return new JsonResponse([
            'id' => $exam->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
