<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use Symfony\Component\HttpFoundation\Request;

final readonly class CrmListRequestHelper
{
    /** @param array<int,string> $filterKeys */
    public function fromRequest(Request $request, array $filterKeys = ['q']): CrmListQueryOptions
    {
        $filters = [];
        foreach ($filterKeys as $key) {
            $filters[$key] = trim((string)$request->query->get($key, ''));
        }

        return new CrmListQueryOptions(
            max(1, $request->query->getInt('page', 1)),
            max(1, min(100, $request->query->getInt('limit', 20))),
            $filters,
        );
    }

    /** @param array<string,string> $filters
     *  @return array<string,string>
     */
    public function activeFilters(array $filters): array
    {
        return array_filter($filters, static fn (string $value): bool => $value !== '');
    }
}
