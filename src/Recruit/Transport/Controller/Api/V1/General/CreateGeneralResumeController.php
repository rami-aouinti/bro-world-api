<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Service\GeneralResumeService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CreateGeneralResumeController
{
    public function __construct(
        private GeneralResumeService $generalResumeService,
    ) {
    }

    #[Route(path: '/v1/recruit/general/resumes', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un CV et permet l’upload optionnel d’un PDF.')]
    #[OA\RequestBody(
        required: false,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(ref: '#/components/schemas/RecruitGeneralResumePayloadInput'),
            ),
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(ref: '#/components/schemas/RecruitGeneralResumePayloadMultipartInput'),
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
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->generalResumeService->create($request, $loggedInUser), JsonResponse::HTTP_CREATED);
    }
}

#[OA\Schema(
    schema: 'RecruitGeneralResumePayloadInput',
    properties: [
        new OA\Property(property: 'experiences', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'educations', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'certifications', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'projects', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'references', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
        new OA\Property(property: 'hobbies', type: 'array', items: new OA\Items(ref: '#/components/schemas/RecruitResumeSectionInput')),
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
)]
final class RecruitGeneralResumePayloadInputSchema
{
}

#[OA\Schema(
    schema: 'RecruitGeneralResumePayloadMultipartInput',
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
)]
final class RecruitGeneralResumePayloadMultipartInputSchema
{
}
