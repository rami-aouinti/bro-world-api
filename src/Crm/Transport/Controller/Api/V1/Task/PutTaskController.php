<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Dto\Command\PutTaskCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class PutTaskController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/tasks/{task}', methods: [Request::METHOD_PUT])]
    #[OA\Put(
        summary: 'Replace Task',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Task replaced.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid JSON payload or invalid date format.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Task or sprint not found in CRM scope.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed or sprint/project scope mismatch.'),
        ],
    )]
    public function __invoke(string $task, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');
        $entity = $this->taskRepository->findOneScopedById($task, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Task not found for this CRM scope.');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, PutTaskCommandDto::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $sprint = null;
        if ($input->sprintId !== null && $input->sprintId !== '') {
            $sprint = $this->sprintRepository->findOneScopedById($input->sprintId, $crm->getId());
            if ($sprint === null) {
                return $this->errorResponseFactory->notFoundReference('sprintId');
            }

            if ($sprint->getProject()?->getId() !== $entity->getProject()?->getId()) {
                return $this->errorResponseFactory->outOfScopeReference('Provided "sprintId" does not belong to the task project.');
            }
        }

        $entity
            ->setTitle((string)$input->title)
            ->setDescription($input->description)
            ->setStatus(TaskStatus::from((string)$input->status))
            ->setPriority(TaskPriority::from((string)$input->priority))
            ->setDueAt($dueAt)
            ->setEstimatedHours(is_numeric($input->estimatedHours) ? (float)$input->estimatedHours : null)
            ->setSprint($sprint);

        $this->taskRepository->save($entity);

        return new JsonResponse(new EntityIdResponseDto($entity->getId())->toArray());
    }
}
