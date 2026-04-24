<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
final readonly class ListProjectGithubBranchesController
{
    public function __construct(
        private CrmGithubService $crmGithubService,
    ) {
    }

    #[Route('/v1/crm/projects/{project}/github/branches', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/projects/{project}/gitlab/branches', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Parameter(ref: '#/components/parameters/q')]
    #[OA\Get(
        description: 'Exécute l action metier List Project Github Branches dans le perimetre de l application CRM.',
        summary: 'List Project Github Branches',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CrmGithubBranch')),
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
    public function __invoke(Project $project, Request $request): JsonResponse
    {
        $repo = (string)$request->query->get('repo', '');

        return new JsonResponse($this->crmGithubService->listBranches(
            $project,
            $repo,
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 30))),
            trim((string)$request->query->get('q', '')),
        ));
    }
}
