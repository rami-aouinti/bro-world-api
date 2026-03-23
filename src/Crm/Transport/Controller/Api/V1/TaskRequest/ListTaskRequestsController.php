<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\TaskRequestReadService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm TaskRequest')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListTaskRequestsController
{
    public function __construct(
        private TaskRequestReadService $taskRequestReadService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests', methods: [Request::METHOD_GET])]
    #[OA\Parameter(ref: '#/components/parameters/applicationSlug')]
    #[OA\Parameter(ref: '#/components/parameters/page')]
    #[OA\Parameter(ref: '#/components/parameters/limit')]
    #[OA\Parameter(ref: '#/components/parameters/q')]
    #[OA\Get(
        summary: 'List Task Requests',
        description: 'Exécute l action metier List Task Requests dans le perimetre de l application CRM.',
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
            new OA\Response(ref: '#/components/responses/Unauthorized401'),
            new OA\Response(ref: '#/components/responses/Forbidden403'),
            new OA\Response(ref: '#/components/responses/NotFound404'),
            new OA\Response(ref: '#/components/responses/ValidationFailed422'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        return new JsonResponse($this->taskRequestReadService->getList($applicationSlug, $request));
    }
}
