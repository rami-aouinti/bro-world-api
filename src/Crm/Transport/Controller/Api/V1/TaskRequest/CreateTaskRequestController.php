<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Message\ProvisionTaskRequestGithubIssue;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmEntityBlogProvisioningService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CreateTaskRequestEntryRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmDateParser;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\General\Application\Message\EntityCreated;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class CreateTaskRequestController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmEntityBlogProvisioningService $crmEntityBlogProvisioningService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmDateParser $crmDateParser,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/task-requests', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Create Task Request',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/CrmTaskRequest'),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Task request created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a8ebfd5d-0fa8-4346-8ca2-ff5b7b1f6657'),
                        new OA\Property(property: 'blogId', type: 'string', format: 'uuid', example: '1d2f3a4b-5c6d-7e8f-9012-3456789abcde', nullable: true),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid JSON payload or invalid date format.',
                content: new OA\JsonContent(
                    examples: [
                        'invalidJson' => new OA\Examples(example: 'invalidJson', summary: 'JSON invalide', value: [
                            'message' => 'Invalid JSON payload.',
                            'errors' => [],
                        ]),
                        'invalidDate' => new OA\Examples(example: 'invalidDate', summary: 'Date invalide', value: [
                            'message' => 'Invalid date format for "resolvedAt".',
                            'errors' => [],
                        ]),
                    ],
                ),
            ),
            new OA\Response(ref: '#/components/responses/NotFound404', response: 404),
            new OA\Response(ref: '#/components/responses/ValidationFailed422', response: 422),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', 'crm-general-core');
        $crm = $this->scopeResolver->resolveOrFail( 'crm-general-core');

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateTaskRequestEntryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $resolvedAt = $this->crmDateParser->parseNullableIso8601($input->resolvedAt, 'resolvedAt');
        if ($resolvedAt instanceof JsonResponse) {
            return $resolvedAt;
        }

        $taskRequest = new TaskRequest();
        $taskRequest->setTitle((string)$input->title)
            ->setDescription($input->description)
            ->setStatus(TaskRequestStatus::tryFrom((string)$input->status) ?? TaskRequestStatus::PENDING)
            ->setResolvedAt($resolvedAt)
            ->setPlannedHours(is_numeric($input->plannedHours) ? (float)$input->plannedHours : 0.0);

        if (is_string($input->taskId)) {
            $task = $this->taskRepository->findOneScopedById($input->taskId, $crm->getId());
            if ($task === null) {
                return $this->errorResponseFactory->notFoundReference('taskId');
            }

            $taskRequest->setTask($task);
        }
        if (is_string($input->repositoryId) && !empty($input->repositoryId)) {
            $repository = $this->crmProjectRepositoryRepository->findOneScopedById($input->repositoryId, $crm->getId());

            if($repository) {
                $taskRequest->setRepository($repository);
            }
        }

        if (is_array($input->assigneeIds)) {
            foreach ($input->assigneeIds as $assigneeId) {
                $assignee = $this->entityManager->getRepository(User::class)->find($assigneeId);
                if (!$assignee instanceof User) {
                    return $this->errorResponseFactory->notFoundReference('assigneeIds');
                }

                $taskRequest->addAssignee($assignee);
            }
        }

        $this->entityManager->persist($taskRequest);
        $this->crmEntityBlogProvisioningService->provision($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new ProvisionTaskRequestGithubIssue($taskRequest->getId()));
        $this->messageBus->dispatch(new EntityCreated('crm_task_request', $taskRequest->getId(), context: [
            'applicationSlug' =>  'crm-general-core',
        ]));

        return new JsonResponse([
            'id' => $taskRequest->getId(),
            'blogId' => $taskRequest->getBlog()?->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
