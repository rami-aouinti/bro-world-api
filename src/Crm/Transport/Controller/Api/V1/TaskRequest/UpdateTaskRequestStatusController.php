<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Application\Service\CrmTaskRequestGithubStatusMapper;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\UpdateTaskRequestStatusRequest;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function hash;
use function sprintf;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class UpdateTaskRequestStatusController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private CrmGithubService $crmGithubService,
        private CrmTaskRequestGithubStatusMapper $statusMapper,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/task-requests/{taskRequest}/status', methods: [Request::METHOD_PATCH, Request::METHOD_PUT])]
        #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        summary: 'Update Task Request Status',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task request status updated.'),
            new OA\Response(response: 400, description: 'Invalid JSON payload.'),
            new OA\Response(response: 404, description: 'Task request not found in CRM scope.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function __invoke(TaskRequest $taskRequest, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', 'crm-general-core');

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = UpdateTaskRequestStatusRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $status = TaskRequestStatus::from((string)$input->status);
        $taskRequest->setStatus($status);
        $this->syncGithubIssueIfMapped($taskRequest, $status);
        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse([
            'id' => $taskRequest->getId(),
            'status' => $taskRequest->getStatus()->value,
        ]);
    }

    private function syncGithubIssueIfMapped(TaskRequest $taskRequest, TaskRequestStatus $status): void
    {
        $githubIssue = $taskRequest->getGithubIssue();
        $project = $taskRequest->getTask()?->getProject();
        $issueNumber = $githubIssue?->getIssueNumber();
        $repositoryFullName = $githubIssue?->getRepositoryFullName();

        if ($githubIssue === null || $project === null || $issueNumber === null || $repositoryFullName === '') {
            return;
        }

        $expectedIssueState = $this->statusMapper->toGithubIssueState($status);
        $sourceMarker = hash('sha256', sprintf('%s:%s:%s', $taskRequest->getId(), $status->value, (new \DateTimeImmutable())->format(DATE_ATOM)));

        $this->crmGithubService->updateIssueState($project, $repositoryFullName, $issueNumber, $expectedIssueState);
        $this->crmGithubService->addIssueComment(
            $project,
            $repositoryFullName,
            $issueNumber,
            sprintf('<!-- crm-source:%s --> CRM status synced: %s', $sourceMarker, $status->value),
        );

        $metadata = $githubIssue->getMetadata();
        $metadata['pendingOutbound'] = [
            'marker' => $sourceMarker,
            'expectedIssueState' => $expectedIssueState,
            'status' => $status->value,
            'origin' => 'crm-api',
            'createdAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ];
        $githubIssue->setMetadata($metadata);
    }
}
