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
final readonly class GetCrmGithubSyncJobController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    #[Route('/v1/crm/github/sync/jobs/{jobId}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'Get CRM GitHub sync job status',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Job status.',
                content: new OA\JsonContent(
                    required: ['id', 'applicationSlug', 'owner', 'status', 'projectsCreated', 'reposAttached', 'issuesImported', 'errorsCount', 'errors'],
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'applicationSlug', type: 'string', example: 'crm-pipeline-pro'),
                        new OA\Property(property: 'owner', type: 'string', example: 'acme-org'),
                        new OA\Property(property: 'startedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'finishedAt', type: 'string', format: 'date-time', nullable: true),
                        new OA\Property(property: 'status', type: 'string', example: 'running'),
                        new OA\Property(property: 'projectsCreated', type: 'integer', example: 3),
                        new OA\Property(property: 'reposAttached', type: 'integer', example: 12),
                        new OA\Property(property: 'issuesImported', type: 'integer', example: 54),
                        new OA\Property(property: 'errorsCount', type: 'integer', example: 1),
                        new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'object')),
                    ],
                    type: 'object',
                )
            ),
        ],
    )]
    public function __invoke(string $jobId, ?string $applicationSlug = null): JsonResponse
    {
        if ($applicationSlug !== null) {
            $this->scopeResolver->resolveOrFail($applicationSlug);
        }

        $job = $this->syncJobRepository->find($jobId);
        if ($job === null || ($applicationSlug !== null && $job->getApplicationSlug() !== $applicationSlug)) {
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
