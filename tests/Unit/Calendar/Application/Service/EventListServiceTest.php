<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar\Application\Service;

use App\Calendar\Application\Service\EventListService;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Domain\Service\Interfaces\MetricsCounterInterface;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

final class EventListServiceTest extends TestCase
{
    public function testGetByUserReturnsCacheHitWithoutRepositoryCall(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);
        $repo->expects(self::never())->method('findByUser');

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())
            ->method('get')
            ->willReturn([
                'items' => [],
                'pagination' => [
                    'page' => 1,
                    'limit' => 20,
                    'totalItems' => 0,
                    'totalPages' => 0,
                ],
            ]);

        $cacheKeyConvention = $this->cacheKeyConventionService($user);
        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $this->createMock(MetricsCounterInterface::class));
        $result = $service->getByUser($user, [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame([
            'title' => 'foo',
        ], $result['filters']);
    }

    public function testGetByUserCacheMissCallsRepository(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->willReturn(0);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 0,
                    'relation' => 'eq',
                ],
                'hits' => [],
            ],
        ]);

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $cacheKeyConvention = $this->cacheKeyConventionService($user);
        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $this->createMock(MetricsCounterInterface::class));
        $result = $service->getByUser($user, [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame(0, $result['pagination']['totalItems']);
    }

    public function testGetByUserFallsBackToDatabaseWhenElasticThrows(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->with(self::anything(), self::anything(), 1, 20, null)->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->with(self::anything(), self::anything(), null)->willReturn(0);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willThrowException(new \RuntimeException('ES down'));

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $metricsCounter = $this->createMock(MetricsCounterInterface::class);
        $metricsCounter->expects(self::once())
            ->method('increment')
            ->with('calendar.elastic_fallback.count', [
                'reason' => 'exception',
            ], 1);

        $cacheKeyConvention = $this->cacheKeyConventionService($user);
        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $metricsCounter);
        $result = $service->getByUser($user, [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame([], $result['items']);
    }

    public function testGetByUserFallsBackToDatabaseWhenElasticReturnsMoreThan1000Hits(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->with(self::anything(), self::anything(), 2, 20, null)->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->with(self::anything(), self::anything(), null)->willReturn(1450);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 1450,
                    'relation' => 'eq',
                ],
                'hits' => [
                    [
                        '_source' => [
                            'id' => 'event-1',
                        ],
                    ],
                ],
            ],
        ]);

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $metricsCounter = $this->createMock(MetricsCounterInterface::class);
        $metricsCounter->expects(self::once())
            ->method('increment')
            ->with('calendar.elastic_fallback.count', [
                'reason' => 'too_many_hits',
            ], 1);

        $cacheKeyConvention = $this->cacheKeyConventionService($user);
        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $metricsCounter);
        $result = $service->getByUser($user, [
            'title' => 'foo',
        ], 2, 20);

        self::assertSame([], $result['items']);
        self::assertSame(1450, $result['pagination']['totalItems']);
        self::assertSame(73, $result['pagination']['totalPages']);
        self::assertSame(2, $result['pagination']['page']);
        self::assertSame(20, $result['pagination']['limit']);
    }

    public function testGetByUserWithTaggableCacheTagsPrivateEvents(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 0,
                    'relation' => 'eq',
                ],
                'hits' => [],
            ],
        ]);

        $item = $this->getMockBuilder(ItemInterface::class)->addMethods(['tag'])->getMock();
        $item->expects(self::once())->method('expiresAfter')->with(120);
        $item->expects(self::once())->method('tag')->with('private_events_user-id');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $cacheKeyConvention = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConvention->expects(self::once())->method('buildPrivateEventKey')->willReturn('private_event_key');
        $cacheKeyConvention->expects(self::once())->method('tagPrivateEvents')->with('user-id')->willReturn('private_events_user-id');
        $cacheKeyConvention->expects(self::never())->method('tagPublicEventsByApplication');

        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $this->createMock(MetricsCounterInterface::class));
        $service->getByUser($user, [
            'title' => 'foo',
        ], 1, 20);
    }

    public function testGetByApplicationSlugWithTaggableCacheTagsPublicEventsByApplication(): void
    {
        $repo = $this->createMock(EventRepositoryInterface::class);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 0,
                    'relation' => 'eq',
                ],
                'hits' => [],
            ],
        ]);

        $item = $this->getMockBuilder(ItemInterface::class)->addMethods(['tag'])->getMock();
        $item->expects(self::once())->method('expiresAfter')->with(120);
        $item->expects(self::once())->method('tag')->with('public_events_app-bro-world');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $cacheKeyConvention = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConvention->expects(self::never())->method('buildPrivateEventKey');
        $cacheKeyConvention->expects(self::never())->method('tagPrivateEvents');
        $cacheKeyConvention->expects(self::once())->method('tagPublicEventsByApplication')->with('app-bro-world')->willReturn('public_events_app-bro-world');

        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $this->createMock(MetricsCounterInterface::class));
        $service->getByApplicationSlug('app-bro-world', [
            'title' => 'foo',
        ], 1, 20);
    }

    public function testGetByUserWithNonTaggableCacheDoesNotCallTag(): void
    {
        $user = $this->mockUser();
        $repo = $this->createMock(EventRepositoryInterface::class);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'total' => [
                    'value' => 0,
                    'relation' => 'eq',
                ],
                'hits' => [],
            ],
        ]);

        $item = $this->getMockBuilder(ItemInterface::class)->addMethods(['tag'])->getMock();
        $item->expects(self::once())->method('expiresAfter')->with(120);
        $item->expects(self::never())->method('tag');

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $cacheKeyConvention = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConvention->expects(self::once())->method('buildPrivateEventKey')->willReturn('private_event_key');
        $cacheKeyConvention->expects(self::never())->method('tagPrivateEvents');
        $cacheKeyConvention->expects(self::never())->method('tagPublicEventsByApplication');

        $service = new EventListService($repo, $cache, $elastic, $cacheKeyConvention, $this->createMock(LoggerInterface::class), $this->createMock(MetricsCounterInterface::class));
        $service->getByUser($user, [
            'title' => 'foo',
        ], 1, 20);
    }

    private function mockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');
        $user->method('getUsername')->willReturn('john');

        return $user;
    }

    private function cacheKeyConventionService(User $user): CacheKeyConventionService
    {
        $cacheKeyConventionService = $this->createMock(CacheKeyConventionService::class);
        $cacheKeyConventionService->expects(self::once())
            ->method('buildPrivateEventKey')
            ->with(
                $user->getUsername(),
                self::callback(static function (array $payload): bool {
                    return isset($payload['accessContext'], $payload['userId'], $payload['filters'])
                        && $payload['accessContext'] === 'user'
                        && $payload['userId'] === 'user-id'
                        && ($payload['filters']['title'] ?? null) === 'foo';
                }),
            )
            ->willReturn('private_event_key');

        $cacheKeyConventionService->expects(self::never())->method('tagPrivateEvents');
        $cacheKeyConventionService->expects(self::never())->method('tagPublicEventsByApplication');

        return $cacheKeyConventionService;
    }
}
