<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
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
final readonly class DeleteGeneralDetachTaskFromSprintController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/general/sprints/{sprint}/tasks/{task}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'task', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Delete(
        summary: 'General - Detach Task From Sprint',
        description: 'Détache une tâche d un sprint dans le périmètre CRM général.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Tâche détachée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(Sprint $sprint, Task $task): JsonResponse
    {
        if ($task->getProject()?->getId() !== $sprint->getProject()?->getId()) {
            return $this->errorResponseFactory->outOfScopeReference('Task and sprint must belong to the same project.');
        }

        $task->setSprint(null);
        $this->taskRepository->save($task);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
