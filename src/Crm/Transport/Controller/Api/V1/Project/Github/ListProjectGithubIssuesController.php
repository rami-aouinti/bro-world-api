<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
final readonly class ListProjectGithubIssuesController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/projects/{project}/github/issues', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'repo', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'rami-aouinti/bro-world-api')]
    #[OA\Parameter(ref: '#/components/parameters/status')]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Get(
        summary: 'List Project GitHub Issues',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CrmGithubIssue')),
                    ],
                    type: 'object',
                ),
            ),
            new OA\Response(ref: '#/components/responses/ValidationFailed422', response: 422),
        ],
    )]
    public function __invoke(Project $project, Request $request): JsonResponse
    {
        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse($this->crmGithubService->listIssues(
            $project,
            (string)$request->query->get('repo', ''),
            (string)$request->query->get('state', 'open'),
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 30))),
        )), $this->errorResponseFactory);
    }
}
