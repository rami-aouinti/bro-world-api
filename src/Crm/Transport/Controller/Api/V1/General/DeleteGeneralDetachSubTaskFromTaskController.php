<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
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
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteGeneralDetachSubTaskFromTaskController
{
    public function __construct(private TaskRepository $taskRepository)
    {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/general/tasks/{task}/subtasks/{subtask}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        summary: 'General - Detach Subtask From Task',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Sous-task détachée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'La sous-task n est pas liée à cette task.'),
        ],
    )]
    public function __invoke(Task $task, Task $subtask): JsonResponse
    {
        if ($subtask->getParentTask()?->getId() !== $task->getId()) {
            throw new HttpException(JsonResponse::HTTP_UNPROCESSABLE_ENTITY, 'Provided subtask is not attached to the provided task.');
        }

        $subtask->setParentTask(null);
        $this->taskRepository->save($subtask);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
