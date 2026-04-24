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
final readonly class GetProjectGithubPullRequestDetailController
{
    public function __construct(
        private CrmGithubService $crmGithubService,
    ) {
    }

    #[Route('/v1/crm/projects/{project}/github/pull-requests/{number}', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/projects/{project}/gitlab/pull-requests/{number}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        description: 'Exécute l action metier Get Project Github Pull Request Detail dans le perimetre de l application CRM.',
        summary: 'Get Project Github Pull Request Detail',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(Project $project, int $number, Request $request): JsonResponse
    {
        return new JsonResponse($this->crmGithubService->getPullRequest($project, (string)$request->query->get('repo', ''), $number));
    }
}
