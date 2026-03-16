<?php

declare(strict_types=1);

namespace App\School\Application\Service;

final readonly class SchoolListQueryOptions
{
    /**
     * @param array<string,string> $filters
     */
    public function __construct(
        public int $page,
        public int $limit,
        public array $filters,
    ) {
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /**
     * @return array{page:int,limit:int,totalItems:int,totalPages:int}
     */
    public function toPaginationMeta(int $totalItems): array
    {
        return [
            'page' => $this->page,
            'limit' => $this->limit,
            'totalItems' => $totalItems,
            'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $this->limit) : 0,
        ];
    }
}
