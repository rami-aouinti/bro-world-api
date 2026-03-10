<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\User;

use App\User\Application\Service\UserPublicListService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'User Management')]
class PublicUserListController
{
    public function __construct(
        private readonly UserPublicListService $userPublicListService
    ) {
    }

    #[Route(path: '/v1/public/users', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Liste publique des utilisateurs (id, email, prénom, nom, photo).',
        security: [],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->userPublicListService->getList($request));
    }
}
