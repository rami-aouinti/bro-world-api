<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\TaskRequestGithubBranch;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\DeleteTaskRequestGithubBranchRequest;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class DeleteTaskRequestGithubBranchController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private TaskRequestRepository $taskRequestRepository,
        private CrmRequestHandler $crmRequestHandler,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmGithubApiErrorResponseFactory $githubErrorResponseFactory,
        private CrmGithubService $crmGithubService,
    ) {
    }

    #[Route('/v1/crm/task-requests/{taskRequest}/github/branches/{branchId}', methods: [Request::METHOD_DELETE])]
        #[OA\Parameter(name: 'taskRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: 'a8f2140e-322e-49e5-94dc-dd86126fef3a')]
    #[OA\Parameter(name: 'branchId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: '5d6c6190-c986-4c78-a08b-90eb29de6316')]
    #[OA\Parameter(name: 'deleteRemote', in: 'query', required: false, schema: new OA\Schema(type: 'boolean', default: false), example: true)]
    #[OA\Delete(
        summary: 'Delete Task Request GitHub Branch',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Local branch binding deleted.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Task request or branch not found in CRM scope.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed or GitHub API error.'),
        ],
    )]
    public function __invoke(TaskRequest $taskRequest, string $branchId, Request $request, ?string $applicationSlug = null): JsonResponse
    {
        $applicationSlug ??= (string)($taskRequest->getTask()?->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug() ?? '');
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $scopedTaskRequest = $this->taskRequestRepository->findOneScopedById($taskRequest->getId(), $crm->getId());
        if ($scopedTaskRequest === null) {
            return $this->errorResponseFactory->notFoundReference('taskRequest');
        }

        $input = $this->crmRequestHandler->mapAndValidate($request->query->all(), DeleteTaskRequestGithubBranchRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $branch = $this->resolveBranch($scopedTaskRequest, $branchId);
        if ($branch === null) {
            return $this->errorResponseFactory->notFoundReference('branchId');
        }

        if ($input->deleteRemote === true) {
            $project = $scopedTaskRequest->getTask()?->getProject();
            if ($project === null) {
                return $this->errorResponseFactory->outOfScopeReference('Task request is not linked to a valid project.');
            }

            try {
                $this->crmGithubService->deleteBranch(
                    $project,
                    $branch->getRepositoryFullName(),
                    $branch->getBranchName(),
                );

                $branch
                    ->setSyncStatus('synced')
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setMetadata($this->buildMetadata($branch, [
                        'deleteRemote' => true,
                        'lastSyncSource' => 'api:delete-task-request-github-branch',
                    ]));
            } catch (CrmGithubApiException $exception) {
                $branch
                    ->setSyncStatus('error')
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setMetadata($this->buildMetadata($branch, [
                        'deleteRemote' => true,
                        'lastSyncSource' => 'api:delete-task-request-github-branch',
                        'apiError' => [
                            'message' => $exception->getMessage(),
                            'errors' => $exception->getErrors(),
                            'statusCode' => $exception->getStatusCode(),
                        ],
                    ]));

                $this->taskRequestRepository->save($scopedTaskRequest, true);

                return $this->githubErrorResponseFactory->fromException($exception);
            }
        }

        $scopedTaskRequest->removeGithubBranch($branch);
        $this->taskRequestRepository->save($scopedTaskRequest, true);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    private function resolveBranch(TaskRequest $taskRequest, string $branchId): ?TaskRequestGithubBranch
    {
        foreach ($taskRequest->getGithubBranches() as $branch) {
            if ($branch->getId() === $branchId) {
                return $branch;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $patch
     *
     * @return array<string,mixed>
     */
    private function buildMetadata(TaskRequestGithubBranch $branch, array $patch): array
    {
        $metadata = $branch->getMetadata();
        if (!is_array($metadata)) {
            $metadata = [];
        }

        return [
            ...$metadata,
            ...$patch,
        ];
    }
}
