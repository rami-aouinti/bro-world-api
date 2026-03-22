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
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class ListProjectGithubProjectItemsController
{
    use HandlesGithubApiExceptions;

    public function __construct(private CrmGithubService $crmGithubService, private CrmGithubApiErrorResponseFactory $errorResponseFactory)
    {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/projects/{projectId}/items', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'projectId', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'PVT_kwDOBfke3c4A9v0F')]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1)]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), example: 20)]
    #[OA\Get(
        summary: 'List items in a GitHub Project (Projects v2).',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Project items fetched.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'GitHub API error.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project, string $projectId, Request $request): JsonResponse
    {
        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse($this->crmGithubService->getProjectItems(
            $project,
            $projectId,
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 20))),
        )), $this->errorResponseFactory);
    }
}
