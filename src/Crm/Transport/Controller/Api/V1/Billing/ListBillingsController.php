<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Role\Domain\Enum\Role;
use DateTimeInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class ListBillingsController
{
    public function __construct(
        private BillingRepository $billingRepository,
        private CrmApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/billings', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'companyId' => trim((string) $request->query->get('companyId', '')),
        ];

        $items = array_map(
            fn (array $item): array => [
                'id' => (string) ($item['id'] ?? ''),
                'companyId' => $item['companyId'] ?? null,
                'label' => (string) ($item['label'] ?? ''),
                'amount' => isset($item['amount']) ? (float) $item['amount'] : 0.0,
                'currency' => (string) ($item['currency'] ?? 'EUR'),
                'status' => (string) ($item['status'] ?? 'pending'),
                'dueAt' => $this->normalizeDateValue($item['dueAt'] ?? null),
                'paidAt' => $this->normalizeDateValue($item['paidAt'] ?? null),
            ],
            $this->billingRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $filters),
        );

        $totalItems = $this->billingRepository->countScopedByCrm($crm->getId(), $filters);

        return new JsonResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int) ceil($totalItems / $limit) : 0,
            ],
            'meta' => [
                'filters' => array_filter($filters),
            ],
        ]);
    }

    private function normalizeDateValue(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value)) {
            return $value;
        }

        return null;
    }
}
