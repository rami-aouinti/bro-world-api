<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Application\Service\ApplicationListService;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

#[AsController]
#[OA\Tag(name: 'Application')]
class PublicApplicationListController
{
    public function __construct(private readonly ApplicationListService $applicationListService)
    {
    }

    #[Route(path: '/v1/application/public', methods: [Request::METHOD_GET])]
    #[OA\Get(
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List public applications with filters and pagination.',
                content: new JsonContent(
                    properties: [
                        new Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                        new Property(property: 'pagination', type: 'object'),
                        new Property(property: 'filters', type: 'object'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    /** @throws Throwable */
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->applicationListService->getPublicList($request));
    }
}
