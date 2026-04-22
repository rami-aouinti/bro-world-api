<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_filter;
use function array_map;
use function ceil;
use function max;
use function min;
use function trim;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class ListGeneralSprintsController
{
    public function __construct(
        private SprintRepository $sprintRepository,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
            'status' => trim((string)$request->query->get('status', '')),
        ];

        $sprints = $this->sprintRepository->findBy([], ['createdAt' => 'DESC'], $limit, ($page - 1) * $limit);
        $items = array_map(static fn ($sprint): array => [
            'id' => $sprint->getId(),
            'name' => $sprint->getName(),
            'status' => $sprint->getStatus()->value,
            'startDate' => $sprint->getStartDate()?->format(DATE_ATOM),
            'endDate' => $sprint->getEndDate()?->format(DATE_ATOM),
            'projectId' => $sprint->getProject()?->getId(),
        ], $sprints);
        $totalItems = (int)count($this->sprintRepository->findAll());

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
