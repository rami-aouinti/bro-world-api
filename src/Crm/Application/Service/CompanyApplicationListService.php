<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\General\Application\Service\CacheKeyConventionService;
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

    /** @return array<string,mixed> */
    public function getList(Request $request, string $applicationSlug, Crm $crm): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = ['q' => trim((string)$request->query->get('q', ''))];
        $cacheKey = $this->cacheKeyConventionService->buildCrmCompanyApplicationListKey($applicationSlug, $page, $limit, $filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $filters, $page, $limit): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmCompanyListByApplicationTag($applicationSlug));
            }

            $qb = $this->companyRepository->createQueryBuilder('company')
                ->andWhere('company.crm = :crm')->setParameter('crm', $crm)
                ->orderBy('company.createdAt', 'DESC')
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit);

            if ($filters['q'] !== '') {
                $qb->andWhere('LOWER(company.name) LIKE LOWER(:q)')->setParameter('q', '%' . $filters['q'] . '%');
            }

            $items = array_map(static fn (Company $company): array => [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'industry' => $company->getIndustry(),
                'website' => $company->getWebsite(),
                'contactEmail' => $company->getContactEmail(),
                'phone' => $company->getPhone(),
            ], $qb->getQuery()->getResult());

            $countQb = $this->companyRepository->createQueryBuilder('company')
                ->select('COUNT(company.id)')
                ->andWhere('company.crm = :crm')->setParameter('crm', $crm);

            if ($filters['q'] !== '') {
                $countQb->andWhere('LOWER(company.name) LIKE LOWER(:q)')->setParameter('q', '%' . $filters['q'] . '%');
            }

            $totalItems = (int)$countQb->getQuery()->getSingleScalarResult();

            return [
                'items' => $items,
                'pagination' => ['page' => $page, 'limit' => $limit, 'totalItems' => $totalItems, 'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0],
                'meta' => ['applicationSlug' => $applicationSlug, 'crmId' => $crm->getId(), 'filters' => array_filter($filters)],
            ];
        });
    }
}
