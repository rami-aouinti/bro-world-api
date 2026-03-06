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
class PrivateApplicationListController
{
    public function __construct(private readonly ApplicationListService $applicationListService)
    {
    }

    #[Route(path: '/v1/application/private', methods: [Request::METHOD_GET])]
    #[OA\Get(
        responses: [
            new OA\Response(
                response: 200,
                description: 'List all public applications and authenticated user applications with filters and pagination.',
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
    /** @throws Throwable */
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->applicationListService->getPrivateList($request, $loggedInUser));
    }
}
