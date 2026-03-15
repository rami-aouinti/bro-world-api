<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListTaskRequestsController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiNormalizer $crmApiNormalizer,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'status' => trim((string)$request->query->get('status', '')),
        ];

        $items = array_map(fn (array $item): array => $this->crmApiNormalizer->normalizeTaskRequestProjection($item), $this->taskRequestRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $filters));
        $totalItems = $this->taskRequestRepository->countScopedByCrm($crm->getId(), $filters);

        return new JsonResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
            'meta' => [
                'filters' => array_filter($filters),
            ],
        ]);
    }
}
