<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\General\Application\Service\CacheKeyConventionService;
use JsonException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class CompanyApplicationListService
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function getList(Request $request, string $applicationSlug, Crm $crm): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'q' => trim((string)$request->query->get('q', '')),
        ];
        $cacheKey = $this->cacheKeyConventionService->buildCrmCompanyApplicationListKey($applicationSlug, $page, $limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmCompanyListByApplicationTag($applicationSlug));
            }

            $items = $this->companyRepository->findScopedProjection($crm->getId(), $limit, ($page - 1) * $limit, $filters);
            $totalItems = $this->companyRepository->countScopedByCrm($crm->getId(), $filters);

            return [
                'items' => $items,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'totalItems' => $totalItems,
                    'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
                ],
                'meta' => [
                    'applicationSlug' => $applicationSlug,
                    'crmId' => $crm->getId(),
                    'filters' => array_filter($filters),
                ],
            ];
        });
    }
}
