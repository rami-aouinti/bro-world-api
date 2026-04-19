<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\CrmGithub;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\CrmGithubSyncJob;
use App\Crm\Infrastructure\Repository\CrmGithubSyncJobRepository;
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
final readonly class GetCrmGithubLatestSyncJobController
{
    private const string GENERAL_APPLICATION_SLUG = 'crm-general-core';

    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/github/sync/jobs/latest', methods: [Request::METHOD_GET])]
    #[Route('/v1/crm/general/github/sync/jobs/latest', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(
        summary: 'Get latest CRM GitHub sync job',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Latest sync job or null when there is no history.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'item', ref: '#/components/schemas/CrmGithubSyncJob', nullable: true),
                    ],
                    type: 'object',
                )
            ),
        ],
    )]
    public function __invoke(?string $applicationSlug = null): JsonResponse
    {
        $applicationSlug ??= self::GENERAL_APPLICATION_SLUG;
        $this->scopeResolver->resolveOrFail($applicationSlug);

        $job = $this->syncJobRepository->findLatestByApplicationSlug($applicationSlug);

        return new JsonResponse([
            'item' => $job instanceof CrmGithubSyncJob ? $this->serializeJob($job) : null,
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeJob(CrmGithubSyncJob $job): array
    {
        return [
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
        ];
    }
}
