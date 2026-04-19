<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Application\Service\PublicGeneralApplicationCatalogService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Application')]
readonly class PublicGeneralApplicationCatalogController
{
    public function __construct(
        private PublicGeneralApplicationCatalogService $catalogService,
    ) {
    }

    #[Route(path: '/v1/application/public/general', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Liste des applications general publiques avec platform, plugins et configurations',
        security: [],
        responses: [
            new OA\Response(response: 200, description: 'Catalogue des applications general publiques'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'items' => $this->catalogService->getCatalog(),
        ]);
    }
}
