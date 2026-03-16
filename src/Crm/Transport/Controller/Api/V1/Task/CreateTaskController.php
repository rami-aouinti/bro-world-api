<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmTaskBlogProvisioningService;
use App\Crm\Application\Exception\CrmOutOfScopeException;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Service\CreateTaskHandler;
use App\Crm\Transport\Request\CreateTaskRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\General\Application\Message\EntityCreated;
use App\Role\Domain\Enum\Role;
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
use Doctrine\ORM\EntityManagerInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateTaskController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmTaskBlogProvisioningService $crmTaskBlogProvisioningService,
        private CrmRequestHandler $crmRequestHandler,
        private CrmDateParser $crmDateParser,
        private CreateTaskHandler $createTaskHandler,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/applications/{applicationSlug}/tasks', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'POST /v1/crm/applications/{applicationSlug}/tasks',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'projectId'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', maxLength: 255, example: 'Configurer le scoring des leads'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 5000, example: 'Ajouter les règles de scoring côté back-office.', nullable: true),
                    new OA\Property(property: 'status', type: 'string', enum: ['todo', 'in_progress', 'blocked', 'done'], example: 'in_progress', nullable: true),
                    new OA\Property(property: 'priority', type: 'string', enum: ['low', 'medium', 'high', 'critical'], example: 'high', nullable: true),
                    new OA\Property(property: 'dueAt', type: 'string', format: 'date-time', example: '2026-03-15T17:00:00+00:00', nullable: true),
                    new OA\Property(property: 'estimatedHours', type: 'number', format: 'float', example: 12.5, nullable: true),
                    new OA\Property(property: 'projectId', type: 'string', format: 'uuid', example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12'),
                    new OA\Property(property: 'sprintId', type: 'string', format: 'uuid', example: '220670e1-4bc3-40da-92bb-89d5dca347a8', nullable: true),
                    new OA\Property(property: 'assigneeIds', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), example: ['7d3c919e-5d4e-406a-a615-ffaf6dddbd85'], nullable: true),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Task created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '8f6a3550-9a07-4f69-9f75-0089f7d83e7f'),
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
                            'message' => 'Invalid date format for "dueAt".',
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
                        'message' => 'Unknown "assigneeIds" in this CRM scope.',
                        'errors' => [],
                    ],
                ),
            ),
            new OA\Response(
                response: 422,
                description: 'Validation or scope consistency failed.',
                content: new OA\JsonContent(
                    examples: [
                        'validationFailed' => new OA\Examples(
                            example: 'validationFailed',
                            summary: 'Validation DTO',
                            value: [
                                'message' => 'Validation failed.',
                                'errors' => [
                                    [
                                        'propertyPath' => 'projectId',
                                        'message' => 'This value should not be blank.',
                                        'code' => 'c1051bb4-d103-4f74-8988-acbcafc7fdc3',
                                    ],
                                ],
                            ],
                        ),
                        'outOfScopeSprint' => new OA\Examples(
                            example: 'outOfScopeSprint',
                            summary: 'Sprint hors projet',
                            value: [
                                'message' => 'Provided "sprintId" does not belong to the provided "projectId".',
                                'errors' => [],
                            ],
                        ),
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

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateTaskRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $dueAt = $this->crmDateParser->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        try {
            $task = $this->createTaskHandler->handle($input, $crm->getId(), $dueAt);
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        } catch (CrmOutOfScopeException $exception) {
            return $this->errorResponseFactory->outOfScopeReference($exception->getMessage());
        }

        $this->entityManager->persist($task);
        $this->crmTaskBlogProvisioningService->provision($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task', $task->getId(), context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse([
            'id' => $task->getId(),
        ], JsonResponse::HTTP_CREATED);
    }

}
