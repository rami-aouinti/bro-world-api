<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\Project;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
final readonly class ListProjectGithubActionsController
{
    public function __construct(private CrmGithubService $crmGithubService)
    {
    }

    #[Route('/v1/crm/projects/{project}/github/actions/workflows', methods: [Request::METHOD_GET])]
    public function workflows(Project $project, Request $request): JsonResponse
    {
        return new JsonResponse($this->crmGithubService->listWorkflows(
            $project,
            (string)$request->query->get('repo', ''),
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 30))),
        ));
    }

    #[Route('/v1/crm/projects/{project}/github/actions/runs', methods: [Request::METHOD_GET])]
    public function runs(Project $project, Request $request): JsonResponse
    {
        $workflowId = $request->query->get('workflowId');

        return new JsonResponse($this->crmGithubService->listWorkflowRuns(
            $project,
            (string)$request->query->get('repo', ''),
            $workflowId !== null ? (int)$workflowId : null,
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 30))),
            $request->query->get('status') !== null ? (string)$request->query->get('status') : null,
        ));
    }
}
