<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class AddTaskRequestAssigneeController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/task-requests/{taskRequest}/assignees/{user}', methods: [Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'taskRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Put(
        summary: 'Add Task Request Assignee',
        description: 'Exécute l action metier Add Task Request Assignee dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest, User $user): JsonResponse
    {
        $taskRequest->addAssignee($user);
        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
