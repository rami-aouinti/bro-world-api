<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\TaskBoardService;
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
final readonly class ListTasksBySprintController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[Route('/v1/crm/tasks/by-sprint/{sprint}', methods: [Request::METHOD_GET])]
        #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        summary: 'List Tasks By Sprint',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Board payload grouped by sprint with sprint.name.'),
        ],
    )]
    public function __invoke(string $sprint): JsonResponse
    {
        return new JsonResponse($this->taskBoardService->listByLatestSprintGlobal());
    }
}
