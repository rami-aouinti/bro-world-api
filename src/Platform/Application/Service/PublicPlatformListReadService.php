<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\General\Application\Service\CacheKeyConventionService;
use App\Platform\Application\Resource\PlatformResource;
use App\Platform\Domain\Entity\Platform;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final readonly class PublicPlatformListReadService
{
    private const int TTL = 600;

    public function __construct(
        private PlatformResource $platformResource,
        private CacheInterface $cache,
        private CacheKeyConventionService $cacheKeyConventionService,
    ) {
    }

    /**
     * @return array<int, Platform>
     */
    public function getPublicEnabled(): array
    {
        $cacheKey = $this->cacheKeyConventionService->buildPublicPlatformsListKey();

        /** @var array<int, Platform> $items */
        $items = $this->cache->get($cacheKey, function (ItemInterface $item): array {
            $item->expiresAfter(self::TTL);

            if (method_exists($item, 'tag') && $this->cache instanceof TagAwareCacheInterface) {
                $item->tag($this->cacheKeyConventionService->tagPublicPlatformsList());
            }

            return $this->platformResource->findPublicEnabled();
        });

        return $items;
    }
}
