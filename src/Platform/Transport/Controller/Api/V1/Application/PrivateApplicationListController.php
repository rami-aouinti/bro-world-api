<?php

declare(strict_types=1);

namespace App\Platform\Transport\Controller\Api\V1\Application;

use App\Platform\Application\Service\ApplicationListService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[AsController]
#[OA\Tag(name: 'Application')]
readonly class PrivateApplicationListController
{
    public function __construct(
        private ApplicationListService $applicationListService
    ) {
    }

    #[Route(path: '/v1/application/private', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Retourne les applications publiques + privées appartenant à l’utilisateur connecté, avec filtres et pagination.',
        summary: 'Liste des applications visibles par l’utilisateur',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1), example: 1),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1), example: 10),
            new OA\Parameter(name: 'title', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'crm'),
            new OA\Parameter(name: 'description', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'lite'),
            new OA\Parameter(name: 'platformName', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'CRM'),
            new OA\Parameter(name: 'platformKey', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'crm'),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liste filtrée et paginée avec indicateur de propriété (isOwner).',
                content: new JsonContent(
                    properties: [
                        new Property(
                            property: 'items',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new Property(property: 'id', type: 'string', example: 'fcb1f1e0-5f6f-4f5b-b7f2-4f5c64f42ea9'),
                                    new Property(property: 'title', type: 'string', example: 'CRM Lite'),
                                    new Property(property: 'slug', type: 'string', example: 'crm-lite'),
                                    new Property(property: 'description', type: 'string', example: 'Application CRM pour équipes commerciales'),
                                    new Property(property: 'photo', type: 'string', nullable: true),
                                    new Property(property: 'status', type: 'string', example: 'active'),
                                    new Property(property: 'private', type: 'boolean', example: true),
                                    new Property(property: 'platformId', type: 'string', nullable: true),
                                    new Property(property: 'platformName', type: 'string', example: 'CRM'),
                                    new Property(property: 'platformKey', type: 'string', example: 'crm'),
                                    new Property(property: 'calendarId', type: 'string', nullable: true, example: '6fdcb5de-57ac-4863-afec-1dd8bb3ef8f6'),
                                    new Property(property: 'chatId', type: 'string', nullable: true, example: 'e233f52e-2dc3-4de5-bc5c-f65c1db4191e'),
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
                                    new Property(property: 'isOwner', type: 'boolean', example: true),
                                ],
                                type: 'object',
                            ),
                        ),
                        new Property(
                            property: 'pagination',
                            properties: [
                                new Property(property: 'page', type: 'integer', example: 1),
                                new Property(property: 'limit', type: 'integer', example: 10),
                                new Property(property: 'totalItems', type: 'integer', example: 2),
                                new Property(property: 'totalPages', type: 'integer', example: 1),
                            ],
                            type: 'object',
                        ),
                        new Property(
                            property: 'filters',
                            properties: [
                                new Property(property: 'title', type: 'string', example: 'crm'),
                                new Property(property: 'platformKey', type: 'string', example: 'crm'),
                            ],
                            type: 'object',
                        ),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    /**
     * @throws Throwable
     */
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->applicationListService->getPrivateList($request, $loggedInUser));
    }
}
