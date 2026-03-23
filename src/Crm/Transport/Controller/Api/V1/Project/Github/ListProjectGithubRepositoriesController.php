<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Role\Domain\Enum\Role;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use OpenApi\Attributes as OA;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListProjectGithubRepositoriesController
{
    public function __construct(
        private CrmGithubService $crmGithubService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories', methods: [Request::METHOD_GET])]
    #[OA\Parameter(ref: '#/components/parameters/applicationSlug')]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Parameter(ref: '#/components/parameters/q')]
    #[OA\Get(
        description: 'Exécute l action metier List Project Github Repositories dans le perimetre de l application CRM.',
        summary: 'List Project Github Repositories',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CrmGithubRepository')),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(ref: '#/components/responses/Unauthorized401', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden403', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound404', response: 404),
            new OA\Response(ref: '#/components/responses/ValidationFailed422', response: 422),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project): JsonResponse
    {
        return new JsonResponse([
            'items' => $this->crmGithubService->listRepositories($project),
        ]);
    }
}
