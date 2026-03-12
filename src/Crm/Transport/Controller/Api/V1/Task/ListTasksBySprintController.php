<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\TaskBoardService;
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
final readonly class ListTasksBySprintController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/by-sprint', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(summary: 'Liste les tasks et task-requests regroupées par sprint')]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        return new JsonResponse($this->taskBoardService->listBySprint($applicationSlug));
    }
}
