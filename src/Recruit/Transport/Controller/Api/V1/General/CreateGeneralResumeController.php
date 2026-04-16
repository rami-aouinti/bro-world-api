<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\ResumeDocumentUploaderService;
use App\Recruit\Application\Service\ResumePayloadService;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit General Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateGeneralResumeController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeDocumentUploaderService $resumeDocumentUploaderService,
        private ResumePayloadService $resumePayloadService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     * @throws JsonException
     */
    #[Route(path: '/v1/recruit/general/resumes', methods: [Request::METHOD_POST])]
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
    #[OA\Response(response: 403, description: 'Access denied')]
    #[OA\Response(response: 404, description: 'Related resource not found')]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->resumePayloadService->extractPayload($request);

        $resume = new Resume()->setOwner($loggedInUser);

        /** @var UploadedFile|null $document */
        $document = $request->files->get('document');
        if ($document instanceof UploadedFile) {
            $documentUrl = $this->resumeDocumentUploaderService->upload($request, $document, '/uploads/resumes');
            $resume->setDocumentUrl($documentUrl);
        }

        $this->resumePayloadService->hydrateResumeSections($resume, $payload);

        $this->resumeRepository->save($resume);

        return new JsonResponse([
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
        ], JsonResponse::HTTP_CREATED);
    }
}
