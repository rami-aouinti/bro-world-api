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
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class GetProjectGithubIssueController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmGithubService $crmGithubService,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/issues/{number}', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/general/projects/{project}/github/issues/{number}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'number', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 42)]
    #[OA\Parameter(name: 'repo', in: 'query', required: true, schema: new OA\Schema(type: 'string'), example: 'rami-aouinti/bro-world-api')]
    #[OA\Get(
        summary: 'Get Project GitHub Issue',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Issue details.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'GitHub API error.'),
        ],
    )]
    public function __invoke(Project $project, int $number, Request $request): JsonResponse
    {
        return $this->withGithubApiErrors(fn (): JsonResponse => new JsonResponse(
            $this->crmGithubService->getIssue($project, (string)$request->query->get('repo', ''), $number),
        ), $this->errorResponseFactory);
    }
}
