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
final readonly class ListProjectGithubCommitsController
{
    public function __construct(private CrmGithubService $crmGithubService)
    {
    }

    #[Route('/v1/crm/projects/{project}/github/commits', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/projects/{project}/gitlab/commits', methods: [Request::METHOD_GET])]
    public function __invoke(Project $project, Request $request): JsonResponse
    {
        return new JsonResponse($this->crmGithubService->listCommits(
            $project,
            (string)$request->query->get('repo', ''),
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 30))),
            $request->query->get('branch') !== null ? (string)$request->query->get('branch') : null,
        ));
    }
}
