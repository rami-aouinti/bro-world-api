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
                        new Property(property: 'items', type: 'array', items: new OA\Items(type: 'object')),
                        new Property(property: 'pagination', type: 'object'),
                        new Property(property: 'filters', type: 'object'),
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
