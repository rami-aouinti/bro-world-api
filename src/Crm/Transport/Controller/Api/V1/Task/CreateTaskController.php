<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Dto\Command\CreateTaskCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Exception\CrmOutOfScopeException;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Service\CreateTaskHandler;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmEntityBlogProvisioningService;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmDateParser;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\General\Application\Message\EntityCreated;
use App\Role\Domain\Enum\Role;
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
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateTaskController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmEntityBlogProvisioningService $crmEntityBlogProvisioningService,
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
    #[Route('/v1/crm/tasks', methods: [Request::METHOD_POST])]
    #[OA\Post(
        summary: 'Create Task',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    'minimalValid' => new OA\Examples(
                        example: 'minimalValid',
                        summary: 'Exemple minimal valide',
                        value: [
                            'title' => 'Créer un tunnel de qualification',
                            'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                        ],
                    ),
                    'fullBusiness' => new OA\Examples(
                        example: 'fullBusiness',
                        summary: 'Exemple métier complet',
                        value: [
                            'title' => 'Configurer le scoring des leads grands comptes',
                            'description' => 'Appliquer les règles MQL/SQL, enrichissement CRM et automatisation du suivi commercial.',
                            'status' => 'in_progress',
                            'priority' => 'high',
                            'dueAt' => '2026-03-15T17:00:00+00:00',
                            'estimatedHours' => 12.5,
                            'projectId' => 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12',
                            'sprintId' => '220670e1-4bc3-40da-92bb-89d5dca347a8',
                            'assigneeIds' => ['7d3c919e-5d4e-406a-a615-ffaf6dddbd85'],
                        ],
                    ),
                ],
                ref: '#/components/schemas/CrmTask',
            ),
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Task created.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '8f6a3550-9a07-4f69-9f75-0089f7d83e7f'),
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
                            'message' => 'Invalid date format for "dueAt".',
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
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, CreateTaskCommandDto::class, mapperMethod: 'fromPostArray');
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
        $this->crmEntityBlogProvisioningService->provision($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task', $task->getId(), context: [
            'applicationSlug' => 'crm-general-core',
        ]));

        return new JsonResponse([
            'id' => $task->getId(),
            'blogId' => $task->getBlog()?->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
