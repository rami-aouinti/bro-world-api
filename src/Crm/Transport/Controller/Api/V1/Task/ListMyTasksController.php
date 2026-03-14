<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\TaskBoardService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::VIEW)]
final readonly class ListMyTasksController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/me/tasks', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(summary: 'Liste mes tasks et task-requests assignées')]
    public function __invoke(string $applicationSlug, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->taskBoardService->listMine($applicationSlug, $loggedInUser));
    }
}
