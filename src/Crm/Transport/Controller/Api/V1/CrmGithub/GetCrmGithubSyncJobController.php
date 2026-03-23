<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\CrmGithub;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\CrmGithubSyncJobRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class GetCrmGithubSyncJobController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/github/sync/jobs/{jobId}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'Get CRM GitHub sync job status',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Job status.', content: new OA\JsonContent(ref: '#/components/schemas/CrmGithubSyncJob')),
            new OA\Response(ref: '#/components/responses/NotFound404', response: JsonResponse::HTTP_NOT_FOUND),
        ],
    )]
    public function __invoke(string $applicationSlug, string $jobId): JsonResponse
    {
        $this->scopeResolver->resolveOrFail($applicationSlug);

        $job = $this->syncJobRepository->find($jobId);
        if ($job === null || $job->getApplicationSlug() !== $applicationSlug) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Sync job not found for this CRM scope.');
        }

        return new JsonResponse([
            'id' => $job->getId(),
            'applicationSlug' => $job->getApplicationSlug(),
            'owner' => $job->getOwner(),
            'startedAt' => $job->getStartedAt()?->format(DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(DATE_ATOM),
            'status' => $job->getStatus(),
            'projectsCreated' => $job->getProjectsCreated(),
            'reposAttached' => $job->getReposAttached(),
            'issuesImported' => $job->getIssuesImported(),
            'errorsCount' => $job->getErrorsCount(),
            'errors' => $job->getErrors(),
        ]);
    }
}
