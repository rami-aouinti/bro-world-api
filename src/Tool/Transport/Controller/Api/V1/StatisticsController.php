<?php

declare(strict_types=1);

namespace App\Tool\Transport\Controller\Api\V1;

use App\Role\Domain\Enum\Role;
use App\Tool\Application\Service\StatisticsService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * @package App\Tool
 */
#[AsController]
#[OA\Tag(name: 'Tools')]
class StatisticsController
{
    public function __construct(
        private readonly StatisticsService $statisticsService,
    ) {
    }

    #[Route(
        path: '/v1/statistics',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted(Role::ADMIN->value)]
    #[OA\Get(
        responses: [
            new OA\Response(
                response: 200,
                description: 'Global statistics for users, applications, plugins and blog posts.',
            ),
            new OA\Response(
                response: 403,
                description: 'Access denied.',
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse($this->statisticsService->getGlobalStatistics());
    }
}
