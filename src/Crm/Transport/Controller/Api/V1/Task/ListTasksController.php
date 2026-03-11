<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\TaskListService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListTasksController
{
    public function __construct(private TaskListService $taskListService)
    {
    }

    #[Route('/v1/crm/tasks', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->taskListService->getList($request));
    }
}
