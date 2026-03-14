<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class CrmReadCacheInvalidator
{
    public function __construct(
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    public function invalidateBilling(string $applicationSlug, string $billingId): void
    {
        if (!$this->cache instanceof TagAwareCacheInterface) {
            return;
        }

        $this->cache->invalidateTags([
            $this->cacheKeyConventionService->crmBillingListTag($applicationSlug),
            $this->cacheKeyConventionService->crmBillingDetailTag($applicationSlug, $billingId),
        ]);
    }
}
