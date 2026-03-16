<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

final readonly class CrmListResponseFactory
{
    public function __construct(
        private CrmListRequestHelper $crmListRequestHelper,
    ) {
    }

    /**
     * @param array<int,mixed> $items
     * @param array<string,mixed> $meta
     * @return array<string,mixed>
     */
    public function create(CrmListQueryOptions $queryOptions, int $totalItems, array $items, array $meta = []): array
    {
        return [
            'items' => $items,
            'pagination' => $queryOptions->toPaginationMeta($totalItems),
            'meta' => [
                ...$meta,
                'filters' => $this->crmListRequestHelper->activeFilters($queryOptions->filters),
            ],
        ];
    }
}
