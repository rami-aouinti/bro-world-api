<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeDocumentUploaderService;
use App\Recruit\Application\Service\ResumeNormalizerService;
use App\Recruit\Application\Service\ResumePayloadService;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ResumeCreateController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeDocumentUploaderService $resumeDocumentUploaderService,
        private ResumePayloadService $resumePayloadService,
        private ResumeNormalizerService $resumeNormalizerService,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(summary: 'Crée un CV et permet l’upload optionnel d’un PDF.')]
    #[OA\RequestBody(
        required: false,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'experiences', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'educations', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'certifications', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'projects', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'references', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'hobbies', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                    ],
                    type: 'object',
                    example: [
                        'experiences' => [[
                            'title' => 'Backend Developer',
                            'description' => 'Symfony API',
                        ]],
                        'skills' => [[
                            'title' => 'PHP',
                            'description' => '8.x',
                        ]],
                    ],
                ),
            ),
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'document', description: 'Fichier CV PDF.', type: 'string', format: 'binary'),
                        new OA\Property(property: 'experiences', description: 'JSON stringifié: [{"title":"...","description":"..."}]', type: 'string'),
                        new OA\Property(property: 'educations', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'skills', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'languages', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'certifications', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'projects', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'references', description: 'JSON stringifié', type: 'string'),
                        new OA\Property(property: 'hobbies', description: 'JSON stringifié', type: 'string'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    #[OA\Response(
        response: 201,
        description: 'Resume created',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'documentUrl', type: 'string', nullable: true),
            ],
            type: 'object',
            example: [
                'id' => '0195f9b4-7c29-7dd2-89f6-2f7d3ef2e9aa',
                'documentUrl' => 'https://localhost/uploads/resumes/0af6fe1514bdbce22f637d970a6e6042.pdf',
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid payload or file format')]
    #[OA\Response(response: 401, description: 'Authentication required')]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = $this->resumePayloadService->extractPayload($request);

        $resume = new Resume()->setOwner($loggedInUser);
        $this->resumePayloadService->applyResumeInformationForCreate($resume, $payload, $loggedInUser);

        /** @var UploadedFile|null $document */
        $document = $request->files->get('document');
        if ($document instanceof UploadedFile) {
            $documentUrl = $this->resumeDocumentUploaderService->upload($request, $document, '/uploads/resumes');
            $resume->setDocumentUrl($documentUrl);
        }

        $this->resumePayloadService->hydrateResumeSections($resume, $payload);

        $this->resumeRepository->save($resume);

        return new JsonResponse($this->resumeNormalizerService->normalize($resume), JsonResponse::HTTP_CREATED);
    }
}

#[OA\Schema(
    schema: 'RecruitResumeSectionInput',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Backend Developer'),
        new OA\Property(property: 'description', type: 'string', example: 'Symfony / API Platform'),
    ],
    type: 'object',
)]
final class RecruitResumeSectionInputSchema
{
}
