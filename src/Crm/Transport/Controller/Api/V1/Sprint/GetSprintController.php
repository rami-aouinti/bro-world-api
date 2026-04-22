<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetSprintController
{
    #[Route('/v1/crm/sprints/{sprint}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'Get Sprint',
        description: 'Exécute l action metier Get Sprint dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'blogId', type: 'string', format: 'uuid', nullable: true),
                    ],
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Sprint $sprint): JsonResponse
    {
        $assignee = $sprint->getAssignees()->toArray();

        foreach ($assignee as $key => $value) {
            $assignee[$key] = $value->toArray();
        }

        return new JsonResponse([
            'id' => $sprint->getId(),
            'project' => $sprint->getProject()->toArray(),
            'name' => $sprint->getName(),
            'goal' => $sprint->getGoal(),
            'status' => $sprint->getStatus()->value,
            'blogId' => $sprint->getBlog()?->getId(),
            'startDate' => $sprint->getStartDate()?->format('Y-m-d'),
            'endDate' => $sprint->getEndDate()?->format('Y-m-d'),
            'tasks' => array_map(
                static fn (Task $task) => [
                    'id' => $task->getId(),
                    'TITLE' => $task->getTitle(),
                    'description' => $task->getDescription(),
                    'status' => $task->getStatus()->value,
                    'dueAt' => $task->getDueAt()?->format(DATE_ATOM),
                ],
                $sprint->getTasks()->toArray()
            ),
            'assignees' => array_map(
                static fn ($assignee) => [
                    'id' => $assignee->getId(),
                    'email' => $assignee->getEmail(),
                    'firstName' => $assignee->getFirstName(),
                    'lastName' => $assignee->getLastName(),
                    'photo' => $assignee->getPhoto(),
                ],
                $sprint->getAssignees()->toArray()
            ),
        ]);
    }
}
