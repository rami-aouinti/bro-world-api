<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Dto\Command\UpdateTaskCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Enum\TaskPriority;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class PatchTaskController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/tasks/{task}', methods: [Request::METHOD_PATCH])]
        #[OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        description: 'Exécute l action metier Patch Task dans le perimetre de l application CRM.',
        summary: 'Patch Task',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(Task $task, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateTaskCommandDto::class, mapperMethod: 'fromPatchArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        if ($input->hasTitle && $input->title !== null) {
            $task->setTitle($input->title);
        }
        if ($input->hasDescription) {
            $task->setDescription($input->description);
        }
        if ($input->hasStatus && $input->status !== null) {
            $status = TaskStatus::tryFrom($input->status);
            if ($status !== null) {
                $task->setStatus($status);
            }
        }
        if ($input->hasPriority && $input->priority !== null) {
            $priority = TaskPriority::tryFrom($input->priority);
            if ($priority !== null) {
                $task->setPriority($priority);
            }
        }
        if ($input->hasDueAt) {
            $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
            if ($dueAt instanceof JsonResponse) {
                return $dueAt;
            }

            $task->setDueAt($dueAt);
        }
        if ($input->hasEstimatedHours) {
            $task->setEstimatedHours(is_numeric($input->estimatedHours) ? (float)$input->estimatedHours : null);
        }
        if ($input->hasSprintId) {
            if ($input->sprintId === null || $input->sprintId === '') {
                $task->setSprint(null);
            } else {
                $sprint = $this->sprintRepository->findOneScopedById($input->sprintId, $crm->getId());
                if ($sprint !== null) {
                    $task->setSprint($sprint);
                }
            }
        }

        $this->taskRepository->save($task);

        return new JsonResponse(new EntityIdResponseDto($task->getId())->toArray());
    }
}
