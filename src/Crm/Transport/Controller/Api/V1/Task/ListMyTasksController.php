<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

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
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListMyTasksController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[Route('/v1/crm/me/tasks', methods: [Request::METHOD_GET])]
        #[OA\Get(
        summary: 'List My Tasks',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'List of assigned tasks and task requests for the current user.'),
        ],
    )]
    public function __invoke(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->taskBoardService->listMine($applicationSlug, $loggedInUser));
    }
}
