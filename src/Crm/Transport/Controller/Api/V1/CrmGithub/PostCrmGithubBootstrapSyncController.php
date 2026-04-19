<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\CrmGithub;

use App\Crm\Application\Message\BootstrapCrmGithubSync;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\CrmGithubSyncJob;
use App\Crm\Infrastructure\Repository\CrmGithubSyncJobRepository;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\PostCrmGithubBootstrapSyncRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm Github')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PostCrmGithubBootstrapSyncController
{
    private const string GENERAL_APPLICATION_SLUG = 'crm-general-core';

    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
        private CrmGithubSyncJobRepository $syncJobRepository,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/github/sync/bootstrap', methods: [Request::METHOD_POST])]
    #[Route('/v1/crm/general/github/sync/bootstrap', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Queue CRM GitHub bootstrap sync',
        description: 'Synchronise les repositories et issues GitHub vers CRM selon issueTarget.'
            . "\n\nRègles de mapping:"
            . "\n- issueTarget=task: issue -> crm_task (title/body/state + estimation priority via labels)."
            . "\n- issueTarget=task_request: issue -> crm_task_request + TaskRequestGithubIssue."
            . "\n\nConversion de statut:"
            . "\n- issue open => TaskStatus::TODO / TaskRequestStatus::PENDING."
            . "\n- issue closed => TaskStatus::DONE et TaskRequestStatus::APPROVED, ou REJECTED si label rejected/reject/declined/crm:rejected ou state_reason=not_planned|rejected.",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                ref: '#/components/schemas/CrmGithubBootstrapSyncRequest',
                examples: [
                    'bootstrapSyncTask' => new OA\Examples(
                        example: 'bootstrapSyncTask',
                        summary: 'Payload exemple bootstrap sync',
                        value: [
                            'token' => 'ghp_xxxxxxxxx',
                            'owner' => 'acme-org',
                            'issueTarget' => 'task',
                            'createPublicProject' => true,
                            'dryRun' => false,
                        ],
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_ACCEPTED,
                description: 'Job queued.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/JobAcceptedResponse',
                    examples: [
                        'queued' => new OA\Examples(
                            example: 'queued',
                            summary: 'Job queued',
                            value: [
                                'jobId' => '0dfde3e4-7095-4fab-bb54-f954ff4c16bd',
                                'status' => 'queued',
                            ],
                        ),
                        'queuedDryRun' => new OA\Examples(
                            example: 'queuedDryRun',
                            summary: 'Dry-run queued',
                            value: [
                                'jobId' => '0dfde3e4-7095-4fab-bb54-f954ff4c16bd',
                                'status' => 'queued',
                                'summary' => [
                                    'mode' => 'dry-run',
                                    'owner' => 'acme-org',
                                    'issueTarget' => 'task',
                                    'createPublicProject' => true,
                                    'plannedActions' => [
                                        'Scan repositories and issues from the configured owner.',
                                        'Map or create CRM entities from GitHub metadata.',
                                        'No persistence changes will be committed in dry-run mode.',
                                    ],
                                ],
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_BAD_REQUEST,
                description: 'Invalid payload.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        'invalidJson' => new OA\Examples(
                            example: 'invalidJson',
                            summary: 'Malformed JSON payload',
                            value: [
                                'message' => 'Invalid JSON payload.',
                                'errors' => [],
                            ],
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_UNAUTHORIZED,
                description: 'Token GitHub invalide ou expiré.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: [
                        'message' => 'Bad credentials',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_FORBIDDEN,
                description: 'Owner inaccessible avec le token fourni.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    example: [
                        'message' => 'Resource not accessible by integration.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Business consistency/import error.',
                content: new OA\JsonContent(
                    ref: '#/components/schemas/ErrorResponse',
                    examples: [
                        'ownerOutOfScope' => new OA\Examples(
                            example: 'ownerOutOfScope',
                            summary: 'Owner inaccessible for current CRM scope',
                            value: [
                                'message' => 'Owner is outside current CRM scope.',
                                'errors' => [
                                    [
                                        'propertyPath' => 'owner',
                                        'message' => 'Owner is outside current CRM scope.',
                                        'code' => 'reference.out_of_scope',
                                    ],
                                ],
                            ],
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(Request $request, ?string $applicationSlug = null): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if ($applicationSlug === null) {
            $applicationSlug = self::GENERAL_APPLICATION_SLUG;
        }

        $this->scopeResolver->resolveOrFail($applicationSlug);

        $input = $this->crmRequestHandler->mapAndValidate($payload, PostCrmGithubBootstrapSyncRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $job = (new CrmGithubSyncJob())
            ->setApplicationSlug($applicationSlug)
            ->setOwner((string)$input->owner)
            ->setStatus('queued')
            ->setParameters([
                'issueTarget' => $input->issueTarget,
                'createPublicProject' => $input->createPublicProject,
                'dryRun' => $input->dryRun,
                'phase' => 'full',
            ]);
        $this->syncJobRepository->save($job, true);
        $jobId = $job->getId();

        $this->messageBus->dispatch(new BootstrapCrmGithubSync(
            jobId: $jobId,
            applicationSlug: $applicationSlug,
            token: (string)$input->token,
            owner: (string)$input->owner,
            issueTarget: $input->issueTarget,
            createPublicProject: $input->createPublicProject,
            dryRun: $input->dryRun,
            phase: 'full',
        ));

        $response = [
            'jobId' => $jobId,
            'status' => 'queued',
        ];

        if ($input->dryRun) {
            $response['summary'] = [
                'mode' => 'dry-run',
                'owner' => $input->owner,
                'issueTarget' => $input->issueTarget,
                'createPublicProject' => $input->createPublicProject,
                'plannedActions' => [
                    'Scan repositories and issues from the configured owner.',
                    'Map or create CRM entities from GitHub metadata.',
                    'No persistence changes will be committed in dry-run mode.',
                ],
            ];
        }

        return new JsonResponse($response, JsonResponse::HTTP_ACCEPTED);
    }
}
