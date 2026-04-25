<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\General\Application\Message\EntityDeleted;
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
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteTaskRequestController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/task-requests/{taskRequest}', methods: [Request::METHOD_DELETE])]
        #[OA\Parameter(name: 'taskRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Delete(
        summary: 'Delete Task Request',
        description: 'Exécute l action metier Delete Task Request dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Ressource supprimée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
        public function __invoke(TaskRequest $taskRequest): JsonResponse
    {
        $this->entityManager->remove($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_task_request', $taskRequest->getId(), context: [
            'applicationSlug' => 'crm-general-core',
        ]));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
