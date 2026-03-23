<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\TaskRequestGithubBranch;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_map;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListTaskRequestGithubBranchesController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private TaskRequestRepository $taskRequestRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}/github/branches', methods: [Request::METHOD_GET])]
    #[OA\Parameter(ref: '#/components/parameters/applicationSlug')]
    #[OA\Parameter(name: 'taskRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: 'a8f2140e-322e-49e5-94dc-dd86126fef3a')]
    #[OA\Get(
        summary: 'List Task Request GitHub Branches',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CrmGithubBranch')),
                    ],
                ),
            ),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound404'),
        ],
    )]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $scopedTaskRequest = $this->taskRequestRepository->findOneScopedById($taskRequest->getId(), $crm->getId());
        if ($scopedTaskRequest === null) {
            return $this->errorResponseFactory->notFoundReference('taskRequest');
        }

        return new JsonResponse([
            'items' => array_map(
                static fn (TaskRequestGithubBranch $branch): array => $branch->toArray(),
                $scopedTaskRequest->getGithubBranches()->toArray(),
            ),
        ]);
    }
}
