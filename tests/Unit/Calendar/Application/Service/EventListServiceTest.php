<?php

declare(strict_types=1);

namespace App\Tests\Unit\Calendar\Application\Service;

use App\Calendar\Application\Service\EventListService;
use App\Calendar\Domain\Repository\Interfaces\EventRepositoryInterface;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

final class EventListServiceTest extends TestCase
{
    public function testGetByUserReturnsCacheHitWithoutRepositoryCall(): void
    {
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

        $service = new EventListService($repo, $cache, $elastic);
        $result = $service->getByUser($this->mockUser(), [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame([
            'title' => 'foo',
        ], $result['filters']);
    }

    public function testGetByUserCacheMissCallsRepository(): void
    {
        $repo = $this->createMock(EventRepositoryInterface::class);
        $repo->expects(self::once())->method('findByUser')->willReturn([]);
        $repo->expects(self::once())->method('countByUser')->willReturn(0);

        $elastic = $this->createMock(ElasticsearchServiceInterface::class);
        $elastic->expects(self::once())->method('search')->willReturn([
            'hits' => [
                'hits' => [],
            ],
        ]);

        $item = $this->createMock(ItemInterface::class);
        $item->expects(self::once())->method('expiresAfter')->with(120);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::once())->method('get')->willReturnCallback(static function (string $key, callable $callback) use ($item): array {
            return $callback($item);
        });

        $service = new EventListService($repo, $cache, $elastic);
        $result = $service->getByUser($this->mockUser(), [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame(0, $result['pagination']['totalItems']);
    }

    public function testGetByUserFallsBackToDatabaseWhenElasticThrows(): void
    {
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

        $service = new EventListService($repo, $cache, $elastic);
        $result = $service->getByUser($this->mockUser(), [
            'title' => 'foo',
        ], 1, 20);

        self::assertSame([], $result['items']);
    }

    private function mockUser(): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-id');

        return $user;
    }
}
