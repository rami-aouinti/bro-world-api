<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Dto\Response\PaginatedListResponseDto;
use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListTasksByApplicationAndSprintController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    #[Route('/v1/crm/sprints/{sprint}/tasks', methods: [Request::METHOD_GET])]
        #[OA\Parameter(name: 'sprint', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1), example: 1)]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1), example: 20)]
    #[OA\Parameter(name: 'search', description: 'Filtre de recherche libre', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(
        description: 'Exécute l action metier List Tasks By Application And Sprint dans le perimetre de l application CRM.',
        summary: 'List Tasks By Application And Sprint',
        responses: [
            new OA\Response(
                response: JsonResponse::HTTP_OK,
                description: 'Opération exécutée avec succès.',
                content: new OA\JsonContent(
                    example: [
                        'items' => [
                            [
                                'id' => '8f6a3550-9a07-4f69-9f75-0089f7d83e7f',
                                'label' => 'CRM item',
                            ],
                        ],
                        'pagination' => [
                            'page' => 1,
                            'limit' => 20,
                            'totalItems' => 57,
                            'totalPages' => 3,
                        ],
                        'meta' => [
                            'filters' => [
                                'search' => 'lead',
                            ],
                        ],
                    ],
                ),
            ),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(
                response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
                description: 'Erreur de validation métier.',
                content: new OA\JsonContent(
                    example: [
                        'message' => 'Validation failed.',
                        'errors' => [
                            [
                                'propertyPath' => 'limit',
                                'message' => 'This value should be less than or equal to 100.',
                                'code' => '2fa2158c-2a7f-484b-98aa-975522539ff8',
                            ],
                        ],
                    ],
                ),
            ),
        ],
    )]
    public function __invoke(Sprint $sprint): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');
        $tasks = $this->taskRepository->findScopedBySprint($crm->getId(), $sprint->getId());

        return new JsonResponse(new PaginatedListResponseDto(
            items: array_map(fn ($task): array => $this->crmApiNormalizer->normalizeTask($task), $tasks),
        )->toArray());
    }
}
