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

    public function testInvalidateSchoolExamCachesByApplicationUsesScopedTagAndDefaultKey(): void
    {
        $cacheKeyConventionService = new CacheKeyConventionService();
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())
            ->method('invalidateTags')
            ->with(['cache_school_exam_list_school-campus-core']);
        $cache->expects(self::once())
            ->method('delete')
            ->with($cacheKeyConventionService->buildSchoolExamListKey('school-campus-core', 1, 20, [
                'q' => '',
                'title' => '',
            ]));

        $service = new CacheInvalidationService($cache, $cacheKeyConventionService);

        $service->invalidateSchoolExamListCaches('school-campus-core');
    }

    public function testInvalidateSchoolExamCachesWithoutApplicationUsesGlobalTagOnly(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())
            ->method('invalidateTags')
            ->with(['cache_school_exam_list']);
        $cache->expects(self::never())->method('delete');

        $service = new CacheInvalidationService($cache, new CacheKeyConventionService());

        $service->invalidateSchoolExamListCaches(null);
    }

    public function testInvalidateSchoolClassCachesByApplicationUsesScopedTagAndDefaultKey(): void
    {
        $cacheKeyConventionService = new CacheKeyConventionService();
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())
            ->method('invalidateTags')
            ->with(['cache_school_class_list_school-campus-core']);
        $cache->expects(self::once())
            ->method('delete')
            ->with($cacheKeyConventionService->buildSchoolClassApplicationListKey('school-campus-core', 1, 20, [
                'q' => '',
            ]));

        $service = new CacheInvalidationService($cache, $cacheKeyConventionService);

        $service->invalidateSchoolClassListCaches('school-campus-core');
    }

    public function testInvalidateSchoolClassCachesWithoutApplicationDoesNothing(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::never())->method('invalidateTags');
        $cache->expects(self::never())->method('delete');

        $service = new CacheInvalidationService($cache, new CacheKeyConventionService());

        $service->invalidateSchoolClassListCaches(null);
    }

    public function testSchoolCacheConventionsAreIsolatedByApplicationSlug(): void
    {
        $service = new CacheKeyConventionService();

        self::assertNotSame(
            $service->schoolExamListTagByApplication('app-alpha'),
            $service->schoolExamListTagByApplication('app-beta'),
        );
        self::assertNotSame(
            $service->schoolClassListByApplicationTag('app-alpha'),
            $service->schoolClassListByApplicationTag('app-beta'),
        );
        self::assertNotSame(
            $service->buildSchoolExamListKey('app-alpha', 1, 20, ['q' => '', 'title' => '']),
            $service->buildSchoolExamListKey('app-beta', 1, 20, ['q' => '', 'title' => '']),
        );
        self::assertNotSame(
            $service->buildSchoolClassApplicationListKey('app-alpha', 1, 20, ['q' => '']),
            $service->buildSchoolClassApplicationListKey('app-beta', 1, 20, ['q' => '']),
        );
    }
}
