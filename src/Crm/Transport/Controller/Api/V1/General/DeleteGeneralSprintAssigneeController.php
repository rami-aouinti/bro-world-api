<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\SprintRepository;
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
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteGeneralSprintAssigneeController
{
    public function __construct(
        private SprintRepository $sprintRepository
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/general/sprints/{sprint}/assignees/{user}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Delete(
        summary: 'Remove Sprint Assignee (General)',
        description: 'Exécute l action metier Remove Sprint Assignee dans le perimetre CRM general.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Assignee retiré avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
        ],
    )]
    public function __invoke(Sprint $sprint, User $user): JsonResponse
    {
        $sprint->removeAssignee($user);
        $this->sprintRepository->save($sprint);

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
