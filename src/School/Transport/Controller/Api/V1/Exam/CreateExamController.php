<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

use App\School\Application\Service\CreateExamService;
use App\School\Domain\Enum\ExamStatus;
use App\School\Domain\Enum\ExamType;
use App\School\Domain\Enum\Term;
use App\School\Transport\Controller\Api\V1\Input\CreateExamInput;
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
final readonly class CreateExamController
{
    public function __construct(
        private CreateExamService $createExamService,
        private SchoolInputValidator $inputValidator,
    ) {
    }

    #[OA\Post(
        path: '/v1/school/applications/{applicationSlug}/exams',
        summary: 'Créer un examen',
        tags: ['School'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['title', 'classId', 'teacherId', 'type', 'status', 'term'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'Examen Mathematiques - Trimestre 1'),
                new OA\Property(property: 'classId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'teacherId', type: 'string', format: 'uuid'),
                new OA\Property(property: 'type', type: 'string', enum: ['QUIZ', 'MIDTERM', 'FINAL', 'ORAL'], example: 'QUIZ'),
                new OA\Property(property: 'status', type: 'string', enum: ['DRAFT', 'PUBLISHED', 'CLOSED'], example: 'DRAFT'),
                new OA\Property(property: 'term', type: 'string', enum: ['TERM_1', 'TERM_2', 'TERM_3'], example: 'TERM_1'),
            ],
        )),
        responses: [
            new OA\Response(response: 201, description: 'Examen créé'),
            new OA\Response(response: 422, description: 'Validation failed'),
        ],
    )]
    #[Route('/v1/school/applications/{applicationSlug}/exams', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = $request->toArray();

        $input = new CreateExamInput();
        $input->title = (string)($payload['title'] ?? '');
        $input->classId = is_string($payload['classId'] ?? null) ? $payload['classId'] : '';
        $input->teacherId = is_string($payload['teacherId'] ?? null) ? $payload['teacherId'] : '';
        $input->type = is_string($payload['type'] ?? null) ? $payload['type'] : '';
        $input->status = is_string($payload['status'] ?? null) ? $payload['status'] : '';
        $input->term = is_string($payload['term'] ?? null) ? $payload['term'] : '';

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $exam = $this->createExamService->create(
            $input->title,
            $input->classId,
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
