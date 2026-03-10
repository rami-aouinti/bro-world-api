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
    public function __construct(
        private readonly ApplicationListService $applicationListService
    ) {
    }

    #[Route(path: '/v1/application/public', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Endpoint paginé avec filtres sur title, description, platformName et platformKey.',
        summary: 'Liste des applications publiques',
        security: [],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1), example: 1),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20), example: 20),
            new OA\Parameter(name: 'title', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'shop'),
            new OA\Parameter(name: 'description', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'growth'),
            new OA\Parameter(name: 'platformName', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'Shop'),
            new OA\Parameter(name: 'platformKey', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'shop'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste des applications publiques filtrée et paginée.',
                content: new JsonContent(
                    properties: [
                        new Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new Property(property: 'id', type: 'string', example: 'fcb1f1e0-5f6f-4f5b-b7f2-4f5c64f42ea9'),
                                    new Property(property: 'title', type: 'string', example: 'Shop Ops App'),
                                    new Property(property: 'slug', type: 'string', example: 'shop-ops-app'),
                                    new Property(property: 'description', type: 'string', example: 'Application operations for shop teams'),
                                    new Property(property: 'photo', type: 'string', nullable: true),
                                    new Property(property: 'status', type: 'string', example: 'active'),
                                    new Property(property: 'private', type: 'boolean', example: false),
                                    new Property(property: 'platformId', type: 'string'),
                                    new Property(property: 'platformName', type: 'string', example: 'Shop'),
                                    new Property(property: 'platformKey', type: 'string', example: 'shop'),
                                    new Property(property: 'pluginKeys', type: 'array', items: new OA\Items(type: 'string', example: 'analytics')),
                                    new Property(
                                        property: 'author',
                                        properties: [
                                            new Property(property: 'id', type: 'string', nullable: true),
                                            new Property(property: 'firstName', type: 'string'),
                                            new Property(property: 'lastName', type: 'string'),
                                            new Property(property: 'photo', type: 'string'),
                                        ],
                                        type: 'object',
                                    ),
                                    new Property(property: 'createdAt', type: 'string', example: '2026-03-06T09:00:00+00:00', nullable: true),
                                    new Property(property: 'isOwner', type: 'boolean', example: false),
                                ],
                                type: 'object',
                            ),
                        ),
                        new Property(
                            property: 'pagination',
                            properties: [
                                new Property(property: 'page', type: 'integer', example: 1),
                                new Property(property: 'limit', type: 'integer', example: 20),
                                new Property(property: 'totalItems', type: 'integer', example: 2),
                                new Property(property: 'totalPages', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                        new Property(
                            property: 'filters',
                            properties: [
                                new Property(property: 'title', type: 'string', example: 'shop'),
                                new Property(property: 'platformKey', type: 'string', example: 'shop'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    /**
     * @throws Throwable
     */
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->applicationListService->getPublicList($request));
    }
}
