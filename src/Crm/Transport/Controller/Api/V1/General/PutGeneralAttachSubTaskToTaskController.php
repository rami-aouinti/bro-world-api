<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskParentRelationGuard;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
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
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutGeneralAttachSubTaskToTaskController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private TaskParentRelationGuard $taskParentRelationGuard,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/general/tasks/{task}/subtasks/{subtask}', methods: [Request::METHOD_PUT])]
    #[OA\Put(
        summary: 'General - Attach Subtask To Task',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Sous-task rattachée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Relation invalide.'),
        ],
    )]
    public function __invoke(Task $task, Task $subtask): JsonResponse
    {
        $this->taskParentRelationGuard->assertCanAssignParent($subtask, $task, 'Provided task and subtask must belong to the same project.');

        $subtask->setParentTask($task);
        $this->taskRepository->save($subtask);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
