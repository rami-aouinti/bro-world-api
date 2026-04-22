<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\TaskBoardService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListGeneralTasksBySprintController
{
    public function __construct(
        private TaskBoardService $taskBoardService,
    ) {
    }

    #[OA\Get(
        summary: 'List General Tasks By Sprint',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Board payload grouped by sprint with tasks and sub tasks.'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->taskBoardService->listBySprintGlobal());
    }
}
