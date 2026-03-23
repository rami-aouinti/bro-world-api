<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CreateTaskRequestGithubBranchRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function explode;
use function implode;
use function ltrim;
use function rawurlencode;
use function sprintf;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class CreateTaskRequestGithubBranchController
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

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}/github/branches', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Create a GitHub branch for a task request issue.',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'feature/task-request-123'),
                    new OA\Property(property: 'sourceBranch', type: 'string', nullable: true, example: 'main'),
                    new OA\Property(property: 'postCommentOnIssue', type: 'boolean', default: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Branch created on GitHub and associated to task request.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Task request not found in CRM scope.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed or GitHub API error.'),
        ],
    )]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $scopedTaskRequest = $this->taskRequestRepository->findOneScopedById($taskRequest->getId(), $crm->getId());
        if ($scopedTaskRequest === null) {
            return $this->errorResponseFactory->notFoundReference('taskRequest');
        }

        $rawContent = trim((string)$request->getContent());
        if ($rawContent === '') {
            $payload = [];
        } else {
            $payload = $this->crmRequestHandler->decodeJson($request);
            if ($payload instanceof JsonResponse) {
                return $payload;
            }
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateTaskRequestGithubBranchRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $githubIssue = $scopedTaskRequest->getGithubIssue();
        $issueNumber = $githubIssue?->getIssueNumber();
        $repositoryFullName = trim((string)$githubIssue?->getRepositoryFullName());
        $project = $scopedTaskRequest->getTask()?->getProject();

        if ($githubIssue === null || $issueNumber === null || $repositoryFullName === '' || $project === null) {
            return $this->errorResponseFactory->outOfScopeReference('Task request is not linked to a valid GitHub issue.');
        }

        $branchName = $this->resolveBranchName($scopedTaskRequest, $issueNumber, $input->name);

        try {
            $createdBranch = $this->crmGithubService->createBranch($project, $repositoryFullName, $branchName, $input->sourceBranch);

            $branchUrl = $this->resolveBranchUrl($repositoryFullName, $branchName, $createdBranch);

            $metadata = $githubIssue->getMetadata();
            $metadata['branch'] = [
                'name' => $branchName,
                'url' => $branchUrl,
                'sourceBranch' => $input->sourceBranch,
                'issueNumber' => $issueNumber,
                'taskRequestId' => $scopedTaskRequest->getId(),
                'createdAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];
            $githubIssue->setMetadata($metadata)->setLastSyncedAt(new DateTimeImmutable());
            $this->taskRequestRepository->save($scopedTaskRequest);

            if ($input->postCommentOnIssue) {
                $this->crmGithubService->addIssueComment(
                    $project,
                    $repositoryFullName,
                    $issueNumber,
                    sprintf('Branch created for this task request: `%s` (%s)', $branchName, $branchUrl),
                );
            }
        } catch (CrmGithubApiException $exception) {
            return $this->githubErrorResponseFactory->fromException($exception);
        }

        return new JsonResponse([
            'branchName' => $branchName,
            'branchUrl' => $branchUrl,
            'issueNumber' => $issueNumber,
            'taskRequestId' => $scopedTaskRequest->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

    private function resolveBranchName(TaskRequest $taskRequest, int $issueNumber, ?string $requestedName): string
    {
        $providedName = trim((string)$requestedName);
        if ($providedName !== '') {
            return $providedName;
        }

        $shortTaskRequestId = str_replace('-', '', substr($taskRequest->getId(), 0, 8));

        return sprintf('task-request/%d-%s', $issueNumber, strtolower($shortTaskRequestId));
    }

    /**
     * @param array<string,mixed> $createdBranch
     */
    private function resolveBranchUrl(string $repositoryFullName, string $branchName, array $createdBranch): string
    {
        $apiUrl = trim((string)($createdBranch['url'] ?? ''));
        if ($apiUrl !== '') {
            return $apiUrl;
        }

        $encodedBranchPath = implode('/', array_map(
            static fn (string $segment): string => rawurlencode($segment),
            array_values(array_filter(explode('/', ltrim($branchName, '/')), static fn (string $segment): bool => $segment !== '')),
        ));

        return sprintf('https://github.com/%s/tree/%s', $repositoryFullName, $encodedBranchPath);
    }
}
