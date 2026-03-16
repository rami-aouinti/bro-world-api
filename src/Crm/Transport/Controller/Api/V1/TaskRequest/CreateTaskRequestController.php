<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmTaskBlogProvisioningService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CreateTaskRequestEntryRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
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
use App\Crm\Transport\Request\CrmDateParser;
use App\Crm\Transport\Request\CrmRequestHandler;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class CreateTaskRequestController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmTaskBlogProvisioningService $crmTaskBlogProvisioningService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmDateParser $crmDateParser,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/task-requests', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'POST /v1/crm/applications/{applicationSlug}/task-requests',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'taskId'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Demande de revue produit'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 5000, example: 'Valider les nouveaux critères de qualification.', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'pending', nullable: true),
                    new OA\Property(property: 'resolvedAt', type: 'string', format: 'date-time', example: '2026-03-20T16:30:00+00:00', nullable: true),
                    new OA\Property(property: 'taskId', type: 'string', format: 'uuid', example: '8f6a3550-9a07-4f69-9f75-0089f7d83e7f'),
                    new OA\Property(property: 'assigneeIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), example: ['7d3c919e-5d4e-406a-a615-ffaf6dddbd85'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Task request created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: 'a8ebfd5d-0fa8-4346-8ca2-ff5b7b1f6657'),
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
            new OA\Response(
                response: 404,
                description: 'Referenced resource not found in CRM scope.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Unknown "taskId" in this CRM scope.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation failed.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'taskId',
                                'message' => 'This is not a valid UUID.',
                                'code' => '51120b12-a2bc-41bf-aa53-cd73daf330d0',
                            ],
                        ],
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

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
            ->setResolvedAt($resolvedAt);

        if (is_string($input->taskId)) {
            $task = $this->taskRepository->findOneScopedById($input->taskId, $crm->getId());
            if ($task === null) {
                return $this->errorResponseFactory->notFoundReference('taskId');
            }

            $taskRequest->setTask($task);
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
        $this->crmTaskBlogProvisioningService->provision($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task_request', $taskRequest->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'id' => $taskRequest->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

}
