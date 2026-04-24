<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\TaskRequestReadService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
final readonly class ListTaskRequestsController
{
    public function __construct(
        private TaskRequestReadService $taskRequestReadService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws \JsonException
     */
    #[Route('/v1/crm/task-requests', methods: [Request::METHOD_GET])]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Parameter(ref: '#/components/parameters/q')]
    #[OA\Get(
        description: 'Exécute l action metier List Task Requests dans le perimetre de l application CRM.',
        summary: 'List Task Requests',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    allOf: [
                        new OA\Schema(ref: '#/components/schemas/PaginatedResponse'),
                        new OA\Schema(properties: [
                            new OA\Property(property: 'items', type: 'array', items: new OA\Items(ref: '#/components/schemas/CrmTaskRequest')),
                        ]),
                    ],
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(ref: '#/components/responses/Unauthorized401', response: 401),
            new OA\Response(ref: '#/components/responses/Forbidden403', response: 403),
            new OA\Response(ref: '#/components/responses/NotFound404', response: 404),
            new OA\Response(ref: '#/components/responses/ValidationFailed422', response: 422),
        ],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->taskRequestReadService->getList('crm-general-core', $request));
    }
}
