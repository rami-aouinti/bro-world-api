<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\TaskRequestGithubBranch;
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
#[OA\Tag(name: 'Crm TaskRequest')]
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

    #[Route('/v1/crm/task-requests/{taskRequest}/github/branches', methods: [Request::METHOD_POST])]
    #[OA\Parameter(ref: '#/components/parameters/applicationSlug')]
    #[OA\Parameter(
        name: 'taskRequest',
        in: 'path',
        required: true,
        description: 'TaskRequest UUID. Future alias: body.taskRequestId on project/github equivalent endpoint.',
        schema: new OA\Schema(type: 'string', format: 'uuid'),
        example: '7cd1c6dd-a211-49f1-8ee0-b8622ff2de3d',
    )]
    #[OA\Post(
        summary: 'Create Task Request GitHub Branch',
        description: 'Creates a GitHub branch from a TaskRequest issue mapping.'
            . "\n\n"
            . 'Try-it-out prerequisites in `/api/doc`:'
            . "\n"
            . '- route param `applicationSlug`: slug of an app linked to CRM fixtures.'
            . "\n"
            . '- route param `taskRequest`: TaskRequest UUID in same application scope.'
            . "\n"
            . '- the task request must already be linked to a GitHub issue.'
            . "\n"
            . '- the linked project must have a GitHub token configured.'
            . "\n"
            . '- optional payload: `name`, `sourceBranch`, `postCommentOnIssue`.',
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                examples: [
                    'autoBranchNameFromIssue' => new OA\Examples(
                        example: 'autoBranchNameFromIssue',
                        summary: 'Création auto du nom de branche depuis issue',
                        value: [
                            'sourceBranch' => 'main',
                            'postCommentOnIssue' => false,
                        ],
                    ),
                    'explicitNameAndIssueComment' => new OA\Examples(
                        example: 'explicitNameAndIssueComment',
                        summary: 'Nom explicite + commentaire automatique sur issue',
                        value: [
                            'name' => 'feature/task-request-2142-gateway-timeout',
                            'sourceBranch' => 'develop',
                            'postCommentOnIssue' => true,
                        ],
                    ),
                ],
                properties: [
                    new OA\Property(property: 'name', type: 'string', nullable: true, example: 'feature/task-request-123'),
                    new OA\Property(property: 'sourceBranch', type: 'string', nullable: true, example: 'main'),
                    new OA\Property(property: 'postCommentOnIssue', type: 'boolean', default: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_CREATED,
                description: 'Branch created on GitHub and associated to task request.',
                content: new OA\JsonContent(
                    required: ['taskRequestId', 'issueNumber', 'repositoryFullName', 'branchName', 'branchUrl', 'sha'],
                    properties: [
                        new OA\Property(property: 'taskRequestId', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'issueNumber', type: 'integer'),
                        new OA\Property(property: 'repositoryFullName', type: 'string'),
                        new OA\Property(property: 'branchName', type: 'string'),
                        new OA\Property(property: 'branchUrl', type: 'string'),
                        new OA\Property(property: 'sha', type: 'string', nullable: true),
                    ],
                    examples: [
                        'createdFromIssueAutoName' => new OA\Examples(
                            example: 'createdFromIssueAutoName',
                            summary: 'Nom calculé automatiquement depuis issue',
                            value: [
                                'taskRequestId' => '7cd1c6dd-a211-49f1-8ee0-b8622ff2de3d',
                                'issueNumber' => 2142,
                                'repositoryFullName' => 'acme/crm-platform',
                                'branchName' => 'task-request/2142-7cd1c6dd',
                                'branchUrl' => 'https://github.com/acme/crm-platform/tree/task-request/2142-7cd1c6dd',
                                'sha' => 'b87f4cab595d65f6d97fd1199f39ca6d8f1c2381',
                            ],
                        ),
                        'createdWithExplicitNameAndComment' => new OA\Examples(
                            example: 'createdWithExplicitNameAndComment',
                            summary: 'Nom explicite + commentaire auto sur issue',
                            value: [
                                'taskRequestId' => '7cd1c6dd-a211-49f1-8ee0-b8622ff2de3d',
                                'issueNumber' => 2142,
                                'repositoryFullName' => 'acme/crm-platform',
                                'branchName' => 'feature/task-request-2142-gateway-timeout',
                                'branchUrl' => 'https://github.com/acme/crm-platform/tree/feature/task-request-2142-gateway-timeout',
                                'sha' => '0a9bcf2f3d62ed2a6d2da0dd01d5db3916efac39',
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_BAD_REQUEST,
                description: 'Business error: missing GitHub token on project.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'GitHub token is not configured on this project.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_NOT_FOUND,
                description: 'Business error: repository not found or inaccessible.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'GitHub resource not found or inaccessible.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_CONFLICT,
                description: 'Business error (target contract): branch already exists.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Branch already exists.',
                        'errors' => [
                            [
                                'resource' => 'Reference',
                                'field' => 'ref',
                                'code' => 'already_exists',
                            ],
                        ],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Business error: task request has no valid issue mapping or GitHub validation failed.',
                content: new OA\JsonContent(
                    examples: [
                        'taskRequestHasNoIssueMapping' => new OA\Examples(
                            example: 'taskRequestHasNoIssueMapping',
                            summary: 'TaskRequest sans mapping issue',
                            value: [
                                'message' => 'Task request is not linked to a valid GitHub issue.',
                                'errors' => [
                                    [
                                        'propertyPath' => 'taskRequest',
                                        'message' => 'Entity is outside current CRM scope.',
                                        'code' => 'reference.out_of_scope',
                                    ],
                                ],
                            ],
                        ),
                        'githubValidationFailedBranchAlreadyExists' => new OA\Examples(
                            example: 'githubValidationFailedBranchAlreadyExists',
                            summary: 'Conflit branche existante (retour GitHub actuel)',
                            value: [
                                'message' => 'GitHub validation failed for this request.',
                                'errors' => [
                                    [
                                        'resource' => 'Reference',
                                        'field' => 'ref',
                                        'code' => 'already_exists',
                                    ],
                                ],
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(TaskRequest $taskRequest, Request $request, ?string $applicationSlug = null): JsonResponse
    {
        $applicationSlug ??= (string)($taskRequest->getTask()?->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug() ?? '');
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

            $syncedAt = new DateTimeImmutable();
            $branchSha = (string)($createdBranch['object']['sha'] ?? $createdBranch['sha'] ?? '');

            $githubBranch = (new TaskRequestGithubBranch())
                ->setTaskRequest($scopedTaskRequest)
                ->setRepositoryFullName($repositoryFullName)
                ->setBranchName($branchName)
                ->setBranchSha($branchSha !== '' ? $branchSha : null)
                ->setBranchUrl($branchUrl)
                ->setIssueNumber($issueNumber)
                ->setSyncStatus('synced')
                ->setLastSyncedAt($syncedAt)
                ->setMetadata([
                    'sourceBranch' => $input->sourceBranch,
                    'createdBranch' => $createdBranch,
                ]);

            $scopedTaskRequest->addGithubBranch($githubBranch);
            $githubIssue->setLastSyncedAt($syncedAt);
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
            'taskRequestId' => $scopedTaskRequest->getId(),
            'repositoryFullName' => $repositoryFullName,
            'branchName' => $branchName,
            'branchUrl' => $branchUrl,
            'sha' => $branchSha !== '' ? $branchSha : null,
            'issueNumber' => $issueNumber,
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
