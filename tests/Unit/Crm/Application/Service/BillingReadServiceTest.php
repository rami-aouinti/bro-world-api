<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Application\Service;

use App\Crm\Application\Service\BillingReadService;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\General\Application\Service\CacheKeyConventionService;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

final class BillingReadServiceTest extends TestCase
{
    public function testListUsesCacheAndFallbackWhenElasticFails(): void
    {
        $billingRepository = $this->createMock(BillingRepository::class);
        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $elasticsearch = $this->createMock(ElasticsearchServiceInterface::class);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $keys = new CacheKeyConventionService();

        $crm = $this->createMock(Crm::class);
        $crm->method('getId')->willReturn('crm-1');
        $scopeResolver->method('resolveOrFail')->with('app')->willReturn($crm);

        $filters = [
            'q' => 'invoice',
            'status' => '',
            'companyId' => '',
        ];

        $elasticsearch->expects(self::exactly(2))->method('search')->willThrowException(new \RuntimeException('es down'));

        $billingRepository->expects(self::once())->method('findScopedProjection')->with('crm-1', 20, 0, $filters)->willReturn([
            [
                'id' => 'b-1',
                'label' => 'Invoice #1',
                'amount' => 10.0,
                'currency' => 'EUR',
                'status' => 'pending',
                'companyId' => 'c-1',
                'dueAt' => null,
                'paidAt' => null,
            ],
        ]);
        $billingRepository->expects(self::once())->method('countScopedByCrm')->with('crm-1', $filters)->willReturn(1);

        $service = new BillingReadService($billingRepository, $scopeResolver, $cache, $keys, $elasticsearch);
        $request = new \Symfony\Component\HttpFoundation\Request([
            'q' => 'invoice',
        ]);

        $first = $service->getList('app', $request);
        $second = $service->getList('app', $request);

        self::assertSame(1, $first['pagination']['totalItems']);
        self::assertSame($first, $second);
    }

    public function testDetailCacheIsInvalidatedAfterMutation(): void
    {
        $billingRepository = $this->createMock(BillingRepository::class);
        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $elasticsearch = $this->createMock(ElasticsearchServiceInterface::class);
        $cache = new TagAwareAdapter(new ArrayAdapter());
        $keys = new CacheKeyConventionService();

        $crm = $this->createMock(Crm::class);
        $crm->method('getId')->willReturn('crm-1');
        $scopeResolver->method('resolveOrFail')->with('app')->willReturn($crm);

        $billing = $this->createMock(Billing::class);
        $version = 'A';
        $billing->method('getId')->willReturn('b-1');
        $billing->method('getCompany')->willReturn(null);
        $billing->method('getLabel')->willReturnCallback(static fn (): string => 'Bill-' . $version);
        $billing->method('getAmount')->willReturn(10.0);
        $billing->method('getCurrency')->willReturn('EUR');
        $billing->method('getStatus')->willReturn('pending');
        $billing->method('getDueAt')->willReturn(null);
        $billing->method('getPaidAt')->willReturn(null);

        $billingRepository->expects(self::exactly(2))->method('findOneScopedById')->with('b-1', 'crm-1')->willReturn($billing);

        $service = new BillingReadService($billingRepository, $scopeResolver, $cache, $keys, $elasticsearch);
        $invalidator = new CrmReadCacheInvalidator($cache, $keys);

        $first = $service->getDetail('app', 'b-1');
        self::assertSame('Bill-A', $first['label']);

        $version = 'B';
        $stale = $service->getDetail('app', 'b-1');
        self::assertSame('Bill-A', $stale['label']);

        $invalidator->invalidateBilling('app', 'b-1');

        $fresh = $service->getDetail('app', 'b-1');
        self::assertSame('Bill-B', $fresh['label']);
    }
}
