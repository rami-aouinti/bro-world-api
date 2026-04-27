<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\TaskBoardService;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListMyAssignedTaskRequestsController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[Route('/v1/crm/me/assigned-task-requests', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'List My Assigned Task Requests',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'List of task requests assigned to the current user.'),
        ],
    )]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        $mine = $this->taskBoardService->listMine('crm-general-core', $loggedInUser);

        return new JsonResponse([
            'items' => $mine['items']['taskRequests'] ?? [],
        ]);
    }
}
