<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class UploadProjectFilesController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmAttachmentUploaderService $attachmentUploaderService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/files', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Upload Project Files',
        description: 'Exécute l action metier Upload Project Files dans le perimetre de l application CRM.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['files'],
                    properties: [
                        new OA\Property(
                            property: 'files',
                            type: 'array',
                            items: new OA\Items(type: 'string', format: 'binary'),
                        ),
                    ],
                ),
                examples: [
                    new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'files' => ['roadmap.pdf'],
                        ],
                    ),
                    new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'files' => ['kickoff-deck.pptx', 'specs-techniques.docx'],
                        ],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Ressource créée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Erreur de validation métier.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'files',
                                'message' => 'Please upload at least one file.',
                                'code' => '2fa2158c-2a7f-484b-98aa-975522539ff8',
                            ],
                        ],
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project, Request $request): JsonResponse
    {
        $uploadedFiles = $this->attachmentUploaderService->upload(
            $request,
            $this->attachmentUploaderService->extractFiles($request),
            '/uploads/crm/projects/' . $project->getId(),
        );

        $attached = [];
        foreach ($uploadedFiles as $file) {
            $attachment = [
                'url' => $file['url'],
                'originalName' => $file['originalName'],
                'mimeType' => $file['mimeType'],
                'size' => $file['size'],
                'extension' => $file['extension'],
                'uploadedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];

            $project->addAttachment($attachment);
            $attached[] = $attachment;
        }

        $this->projectRepository->save($project);

        return new JsonResponse([
            'files' => $attached,
        ], JsonResponse::HTTP_CREATED);
    }
}
