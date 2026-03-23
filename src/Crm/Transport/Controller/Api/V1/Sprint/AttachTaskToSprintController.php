<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\SprintRepository;
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
final readonly class AttachTaskToSprintController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private SprintRepository $sprintRepository,
        private TaskRepository $taskRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{sprintId}/tasks/{taskId}', methods: [Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sprintId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'taskId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Put(
        summary: 'Attach Task To Sprint',
        description: 'Exécute l action metier Attach Task To Sprint dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $sprintId, string $taskId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $sprint = $this->sprintRepository->findOneScopedById($sprintId, $crm->getId());
        if ($sprint === null) {
            return $this->errorResponseFactory->notFoundReference('sprintId');
        }

        $task = $this->taskRepository->findOneScopedById($taskId, $crm->getId());
        if ($task === null) {
            return $this->errorResponseFactory->notFoundReference('taskId');
        }

        if ($task->getProject()?->getId() !== $sprint->getProject()?->getId()) {
            return $this->errorResponseFactory->outOfScopeReference('Task and sprint must belong to the same project.');
        }

        $task->setSprint($sprint);
        $this->taskRepository->save($task);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
