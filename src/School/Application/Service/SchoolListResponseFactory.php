<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\School\Application\Serializer\SchoolApiResponseSerializer;

final readonly class SchoolListResponseFactory
{
    public function __construct(
        private SchoolApiResponseSerializer $responseSerializer,
        private SchoolListRequestHelper $listRequestHelper,
    ) {
    }

    /**
     * @param array<int,array<string,mixed>> $items
     * @param array<string,mixed> $context
     *
     * @return array<string,mixed>
     */
    public function create(SchoolListQueryOptions $queryOptions, int $totalItems, array $items, array $context = []): array
    {
        return $this->responseSerializer->list(
            $items,
            $queryOptions->toPaginationMeta($totalItems),
            [
                ...$context,
                'filters' => $this->listRequestHelper->activeFilters($queryOptions->filters),
            ],
        );
    }
}
