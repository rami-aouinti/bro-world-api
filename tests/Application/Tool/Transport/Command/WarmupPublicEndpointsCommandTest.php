<?php

declare(strict_types=1);

namespace App\Tests\Application\Tool\Transport\Command;

use App\General\Application\Service\CacheInvalidationService;
use App\Tool\Application\Service\Elastic\Interfaces\ReindexAllDomainsServiceInterface;
use App\Tool\Application\Service\Warmup\WarmupPublicEndpointsConfigProvider;
use App\Tool\Application\Service\Warmup\WarmupPublicEndpointsObservabilityService;
use App\Tool\Transport\Command\WarmupPublicEndpointsCommand;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class WarmupPublicEndpointsCommandTest extends TestCase
{
    public function testEsUnavailableSkipsHttpWarmupAndReturnsFailure(): void
    {
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);
        $cacheInvalidationService->expects($this->once())->method('invalidatePublicPageCaches');
        $cacheInvalidationService->expects($this->once())->method('invalidatePublicPlatformListCaches');
        $cacheInvalidationService->expects($this->once())->method('invalidateBlogCaches')->with(null);
        $cacheInvalidationService->expects($this->once())->method('invalidateApplicationListCaches');
        $cacheInvalidationService->expects($this->once())->method('invalidateShopProductListCaches');

        $reindexService = $this->createMock(ReindexAllDomainsServiceInterface::class);
        $reindexService->expects($this->once())
            ->method('reindexAllDomains')
            ->willReturn(Command::FAILURE);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->never())->method('request');

        $command = $this->createCommand($cacheInvalidationService, $reindexService, $httpClient);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Elasticsearch reindex step failed.', $tester->getDisplay());
    }

    public function testCriticalEndpointHttp500FailsRun(): void
    {
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        $reindexService = $this->createMock(ReindexAllDomainsServiceInterface::class);
        $reindexService->expects($this->once())
            ->method('reindexAllDomains')
            ->willReturn(Command::SUCCESS);

        $criticalResponse = $this->createMock(ResponseInterface::class);
        $criticalResponse->method('getStatusCode')->willReturn(500);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', 'https://example.test/healthz-critical', $this->anything())
            ->willReturn($criticalResponse);

        $command = $this->createCommand(
            $cacheInvalidationService,
            $reindexService,
            $httpClient,
            <<<YAML
max_concurrency: 1
timeout_seconds: 0.1
retry_max: 1
success_threshold_ms: 500
critical:
  - path: /healthz-critical
secondary: []
YAML
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('[FAIL] https://example.test/healthz-critical (status=500', $tester->getDisplay());
        self::assertStringContainsString('At least one critical endpoint failed during warmup.', $tester->getDisplay());
    }

    public function testSecondaryTimeoutDoesNotFailRunButIsReportedInSummary(): void
    {
        $cacheInvalidationService = $this->createMock(CacheInvalidationService::class);

        $reindexService = $this->createMock(ReindexAllDomainsServiceInterface::class);
        $reindexService->expects($this->once())
            ->method('reindexAllDomains')
            ->willReturn(Command::SUCCESS);

        $criticalResponse = $this->createMock(ResponseInterface::class);
        $criticalResponse->method('getStatusCode')->willReturn(200);

        $timeoutResponse = $this->createMock(ResponseInterface::class);
        $timeoutResponse->method('getStatusCode')->willThrowException(new class ('timeout') extends \RuntimeException implements ExceptionInterface {
        });

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnCallback(static fn (string $method, string $url): ResponseInterface => match ($url) {
                'https://example.test/healthz-critical' => $criticalResponse,
                'https://example.test/healthz-secondary' => $timeoutResponse,
                default => throw new \LogicException('Unexpected URL ' . $url),
            });

        $command = $this->createCommand(
            $cacheInvalidationService,
            $reindexService,
            $httpClient,
            <<<YAML
max_concurrency: 2
timeout_seconds: 0.1
retry_max: 1
success_threshold_ms: 500
critical:
  - path: /healthz-critical
secondary:
  - path: /healthz-secondary
YAML
        );

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('[FAIL] https://example.test/healthz-secondary (status=n/a', $tester->getDisplay());
        self::assertStringContainsString('Failures', $tester->getDisplay());
        self::assertStringContainsString('Public endpoints warmup completed without critical failures.', $tester->getDisplay());
    }

    private function createCommand(
        CacheInvalidationService $cacheInvalidationService,
        ReindexAllDomainsServiceInterface $reindexService,
        HttpClientInterface $httpClient,
        string $yamlConfig = <<<YAML
max_concurrency: 1
timeout_seconds: 0.1
retry_max: 1
success_threshold_ms: 500
critical:
  - path: /healthz-critical
secondary: []
YAML,
    ): WarmupPublicEndpointsCommand {
        $configProvider = new WarmupPublicEndpointsConfigProvider($this->createWarmupConfigFile($yamlConfig));

        $observabilityService = new WarmupPublicEndpointsObservabilityService(
            monitoringLogger: new NullLogger(),
            appCache: new ArrayAdapter(),
            httpClient: $this->createMock(HttpClientInterface::class),
            notifier: null,
            warmupCriticalFailureWindowSeconds: 300,
            warmupCriticalFailureAlertThreshold: 3,
            warmupCriticalFailureSlackWebhook: '',
        );

        return new WarmupPublicEndpointsCommand(
            cacheInvalidationService: $cacheInvalidationService,
            reindexAllDomainsService: $reindexService,
            httpClient: $httpClient,
            configProvider: $configProvider,
            warmupPublicEndpointsBaseUrl: 'https://example.test',
            lockFactory: new LockFactory(new InMemoryStore()),
            logger: new NullLogger(),
            observabilityService: $observabilityService,
        );
    }

    private function createWarmupConfigFile(string $yamlContent): string
    {
        $filePath = sys_get_temp_dir() . '/warmup-public-endpoints-config-' . uniqid('', true) . '.yaml';
        file_put_contents($filePath, $yamlContent);

        return $filePath;
    }
}
