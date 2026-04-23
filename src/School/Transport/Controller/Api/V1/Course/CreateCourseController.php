<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Course;

use App\School\Application\Service\CreateCourseService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Application\Service\SchoolCourseAttachmentUploaderService;
use App\School\Transport\Controller\Api\V1\Input\CreateCourseInput;
use App\School\Transport\Controller\Api\V1\Input\SchoolInputValidator;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;
use function is_string;
use function str_contains;
use function trim;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateCourseController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private CreateCourseService $createCourseService,
        private SchoolInputValidator $inputValidator,
        private SchoolCourseAttachmentUploaderService $attachmentUploader,
    ) {
    }

    #[OA\Post(
        path: '/v1/school/courses',
        summary: 'Créer un cours avec contenu HTML et pièces jointes optionnelles',
        tags: ['School'],
        requestBody: new OA\RequestBody(
            required: true,
            content: [
                new OA\MediaType(
                    mediaType: 'application/json',
                    schema: new OA\Schema(
                        required: ['name', 'classId'],
                        properties: [
                            new OA\Property(property: 'name', type: 'string', example: 'Algorithmique avancée'),
                            new OA\Property(property: 'classId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'teacherId', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'contentHtml', type: 'string', nullable: true, example: '<h2>Chapitre 1</h2><p>...</p>'),
                        ],
                    ),
                ),
                new OA\MediaType(
                    mediaType: 'multipart/form-data',
                    schema: new OA\Schema(
                        required: ['name', 'classId'],
                        properties: [
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'classId', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'teacherId', type: 'string', format: 'uuid', nullable: true),
                            new OA\Property(property: 'contentHtml', type: 'string', nullable: true),
                            new OA\Property(property: 'attachments[]', type: 'array', items: new OA\Items(type: 'string', format: 'binary')),
                        ],
                    ),
                ),
            ],
        ),
        responses: [
            new OA\Response(response: 201, description: 'Cours créé'),
            new OA\Response(response: 403, description: 'Forbidden', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError')),
            new OA\Response(response: 404, description: 'Not found', content: new OA\JsonContent(ref: '#/components/schemas/SchoolError')),
            new OA\Response(response: 422, description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/SchoolValidationError')),
        ],
    )]
    #[Route('/v1/school/courses', methods: [Request::METHOD_POST], defaults: ['applicationSlug' => 'general'])]
        public function __invoke(string $applicationSlug, ?User $loggedInUser, Request $request): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $payload = $this->extractPayload($request);

        $input = new CreateCourseInput();
        $input->name = (string)($payload['name'] ?? '');
        $input->classId = is_string($payload['classId'] ?? null) ? $payload['classId'] : '';
        $input->teacherId = is_string($payload['teacherId'] ?? null) ? $payload['teacherId'] : null;
        $input->contentHtml = is_string($payload['contentHtml'] ?? null) ? $payload['contentHtml'] : null;

        $validationResponse = $this->inputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $uploadedAttachments = $this->attachmentUploader->upload(
            $request,
            $this->extractAttachments($request),
            '/uploads/school/courses',
        );

        $course = $this->createCourseService->create(
            $school,
            $input->name,
            $input->classId,
            $input->teacherId,
            $this->normalizeNullableString($input->contentHtml),
            $uploadedAttachments,
        );

        return new JsonResponse([
            'id' => $course->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string,mixed>
     */
    private function extractPayload(Request $request): array
    {
        $contentType = (string)$request->headers->get('Content-Type', '');

        if (str_contains($contentType, 'multipart/form-data')) {
            return $request->request->all();
        }

        return $request->toArray();
    }

    /**
     * @return list<UploadedFile>
     */
    private function extractAttachments(Request $request): array
    {
        $attachments = $request->files->get('attachments');

        if ($attachments instanceof UploadedFile) {
            return [$attachments];
        }

        if (!is_array($attachments)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $file): ?UploadedFile => $file instanceof UploadedFile ? $file : null,
            $attachments,
        )));
    }

    private function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
