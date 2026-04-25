<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeAiParsingService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ResumeParsePdfController
{
    public function __construct(
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes/parse-pdf', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Upload un CV PDF, extrait le texte et retourne les données structurées via AI locale.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['document'],
                properties: [
                    new OA\Property(property: 'document', description: 'Fichier PDF du CV.', type: 'string', format: 'binary'),
                ],
                type: 'object',
            ),
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Données extraites du CV.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'data',
                    properties: [
                        new OA\Property(property: 'user', type: 'object'),
                        new OA\Property(property: 'experiences', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'educations', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'string')),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
            example: [
                'data' => [
                    'user' => [
                        'fullName' => 'John Doe',
                        'email' => 'john.doe@example.com',
                        'phone' => '+33 6 00 00 00 00',
                        'address' => 'Paris, France',
                        'links' => ['https://github.com/johndoe'],
                    ],
                    'experiences' => [
                        [
                            'title' => 'Backend Developer',
                            'company' => 'Acme',
                            'startDate' => '2023-01',
                            'endDate' => '2025-03',
                            'description' => 'Symfony API platform',
                        ],
                    ],
                    'educations' => [],
                    'skills' => ['PHP', 'Symfony'],
                ],
            ],
        ),
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $document = $request->files->get('document');
        if (!$document instanceof UploadedFile) {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "document" is required and must be a file.');
        }

        if ($document->getMimeType() !== 'application/pdf' && $document->guessExtension() !== 'pdf') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Uploaded file must be a PDF.');
        }

        $parsed = $this->resumeAiParsingService->parsePdf($document->getPathname());

        return new JsonResponse([
            'data' => $parsed,
        ]);
    }
}
