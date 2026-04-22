<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\ProjectReadService;
use App\Crm\Domain\Entity\Project;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class GetProjectController
{
    public function __construct(
        private ProjectReadService $projectReadService,
    ) {
    }

    #[Route('/v1/crm/projects/{project}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'Get Project',
        description: 'Exécute l action metier Get Project dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'blogId', type: 'string', format: 'uuid', nullable: true),
                    ],
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project): JsonResponse
    {
        $payload = $this->projectReadService->getDetail($applicationSlug, $project);
        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found for this CRM scope.');
        }

        return new JsonResponse($payload);
    }
}
