<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Company;
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
        private CrmListRequestHelper $listRequestHelper,
        private CrmListResponseFactory $listResponseFactory,
    ) {
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function list(Request $request, string $applicationSlug, Crm $crm): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);
        $cacheKey = $this->cacheKeyConventionService->buildCrmCompanyApplicationListKey($applicationSlug, $queryOptions->page, $queryOptions->limit, $queryOptions->filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($applicationSlug, $crm, $queryOptions): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmCompanyListByApplicationTag($applicationSlug));
            }

            $items = $this->companyRepository->findScopedProjection($crm->getId(), $queryOptions->limit, $queryOptions->offset(), $queryOptions->filters);
            $totalItems = $this->companyRepository->countScopedByCrm($crm->getId(), $queryOptions->filters);

            return $this->listResponseFactory->create($queryOptions, $totalItems, $items, [
                'applicationSlug' => $applicationSlug,
                'crmId' => $crm->getId(),
            ]);
        });
    }

    /**
     * @return array<string,mixed>
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    public function listGlobal(Request $request): array
    {
        $queryOptions = $this->listRequestHelper->fromRequest($request, ['q']);
        $cacheKey = $this->cacheKeyConventionService->buildCrmCompanyApplicationListKey('general', $queryOptions->page, $queryOptions->limit, $queryOptions->filters);

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($queryOptions): array {
            $item->expiresAfter(120);
            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->crmCompanyListByApplicationTag('general'));
            }

            $items = $this->companyRepository->findBy([], ['createdAt' => 'DESC'], $queryOptions->limit, $queryOptions->offset());
            $totalItems = (int)count($this->companyRepository->findAll());

            $normalizedItems = array_map(
                static fn (Company $company): array => [
                    'id' => $company->getId(),
                    'name' => $company->getName(),
                    'industry' => $company->getIndustry(),
                    'website' => $company->getWebsite(),
                    'contactEmail' => $company->getContactEmail(),
                    'phone' => $company->getPhone(),
                ],
                $items
            );

            return $this->listResponseFactory->create($queryOptions, $totalItems, $normalizedItems);
        });
    }

    /**
     * @return array<string,mixed>
     */
    public function getGlobalDetail(Company $company): array
    {
        return [
            'id' => $company->getId(),
            'name' => $company->getName(),
            'industry' => $company->getIndustry(),
            'website' => $company->getWebsite(),
            'contactEmail' => $company->getContactEmail(),
            'phone' => $company->getPhone(),
        ];
    }
}
