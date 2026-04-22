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
final readonly class GetProjectGithubCommitController
{
    public function __construct(private CrmGithubService $crmGithubService)
    {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/commits/{sha}', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/general/projects/{project}/github/commits/{sha}', methods: [Request::METHOD_GET])]
    public function __invoke(Project $project, string $sha, Request $request): JsonResponse
    {
        return new JsonResponse($this->crmGithubService->getCommit(
            $project,
            (string)$request->query->get('repo', ''),
            $sha,
        ));
    }
}
