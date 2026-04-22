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

use function max;
use function min;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
final readonly class ListCrmGithubSyncJobsController
{
    private const string GENERAL_APPLICATION_SLUG = 'crm-general-core';

    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    #[Route('/v1/crm/github/sync/jobs', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Get(
        summary: 'List CRM GitHub sync jobs',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'List of sync jobs for CRM scope.',
                content: new OA\JsonContent(
                    type: 'object',
                )
            ),
        ],
    )]
    public function __invoke(Request $request, ?string $applicationSlug = null): JsonResponse
    {
        $applicationSlug ??= self::GENERAL_APPLICATION_SLUG;
        $this->scopeResolver->resolveOrFail($applicationSlug);

        $status = $request->query->getString('status', '');
        $status = $status !== '' ? $status : null;
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $jobs = $this->syncJobRepository->findRecentByApplicationSlug($applicationSlug, $limit, $status);

        return new JsonResponse([
            'items' => array_map(self::serializeJob(...), $jobs),
        ]);
    }

    /**
     * @return array<string,mixed>
     */
    private static function serializeJob(CrmGithubSyncJob $job): array
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
