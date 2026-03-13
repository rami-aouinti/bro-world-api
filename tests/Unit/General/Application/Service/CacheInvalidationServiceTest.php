<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Application\Service;

use App\General\Application\Service\CacheInvalidationService;
use App\General\Application\Service\CacheKeyConventionService;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class CacheInvalidationServiceTest extends TestCase
{
    public function testInvalidateBlogCachesBuildsPublicAndUniquePrivateTags(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())
            ->method('invalidateTags')
            ->with([
                'cache_public_blog',
                'cache_public_blog_my-app',
                'cache_private_actor_blog',
                'cache_private_author_blog',
            ]);

        $service = new CacheInvalidationService($cache, new CacheKeyConventionService());

        $service->invalidateBlogCaches('my-app', ['actor', 'author', 'actor', '', null]);
    }

    public function testInvalidateSchoolExamCachesByApplicationUsesScopedTag(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())
            ->method('invalidateTags')
            ->with(['cache_school_exam_list_school-campus-core']);
        $cache->expects(self::once())
            ->method('delete')
            ->with(self::stringStartsWith('school_exam_list_'));

        $service = new CacheInvalidationService($cache, new CacheKeyConventionService());

        $service->invalidateSchoolExamListCaches('school-campus-core');
    }
}
