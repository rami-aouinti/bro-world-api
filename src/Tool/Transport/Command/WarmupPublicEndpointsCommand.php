<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command;

use App\General\Application\Service\CacheInvalidationService;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\Service\Elastic\Interfaces\ReindexAllDomainsServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function count;
use function microtime;
use function number_format;
use function rtrim;
use function sprintf;

#[AsCommand(
    name: self::NAME,
    description: 'Invalidate public caches, reindex Elasticsearch domains and warm public HTTP endpoints.',
)]
final class WarmupPublicEndpointsCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'app:warmup:public-endpoints';

    private const float REQUEST_TIMEOUT = 2.5;
    private const int MAX_ATTEMPTS = 2;

    /**
     * @var list<array{path: string, critical: bool}>
     */
    private const array ENDPOINTS = [
        ['path' => '/api/health', 'critical' => true],
        ['path' => '/api/version', 'critical' => true],
        ['path' => '/api/v1/localization/language', 'critical' => true],
        ['path' => '/api/v1/localization/locale', 'critical' => false],
        ['path' => '/api/v1/localization/timezone', 'critical' => false],
    ];

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidationService,
        private readonly ReindexAllDomainsServiceInterface $reindexAllDomainsService,
        private readonly HttpClientInterface $httpClient,
        private readonly string $warmupPublicEndpointsBaseUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);
        $totalStart = microtime(true);

        $io->section('1/4 Cache invalidation');
        $this->invalidateTargetedCaches();
        $io->success('Targeted public caches were invalidated.');

        $io->section('2/4 Elasticsearch reindex');
        $reindexStatus = $this->reindexAllDomainsService->reindexAllDomains($input, $output);
        if ($reindexStatus !== Command::SUCCESS) {
            $io->error('Elasticsearch reindex step failed.');

            return Command::FAILURE;
        }
        $io->success('Elasticsearch reindex completed.');

        $io->section('3/4 HTTP endpoints warmup');
        $results = [];
        foreach (self::ENDPOINTS as $endpoint) {
            $result = $this->warmEndpoint($endpoint['path'], $endpoint['critical']);
            $results[] = $result;

            $state = $result['success'] ? 'OK' : 'FAIL';
            $io->writeln(sprintf(
                '[%s] %s (status=%s, attempts=%d, latency=%sms)',
                $state,
                $result['url'],
                $result['statusCode'] !== null ? (string) $result['statusCode'] : 'n/a',
                $result['attempts'],
                number_format($result['latencyMs'], 2, '.', ''),
            ));
        }

        $io->section('4/4 Summary');
        $successCount = count(array_filter($results, static fn (array $item): bool => $item['success']));
        $failureCount = count($results) - $successCount;
        $criticalFailures = count(array_filter(
            $results,
            static fn (array $item): bool => $item['critical'] && !$item['success']
        ));

        $totalLatencyMs = 0.0;
        foreach ($results as $item) {
            $totalLatencyMs += $item['latencyMs'];
        }

        $averageLatencyMs = $results !== [] ? ($totalLatencyMs / count($results)) : 0.0;
        $durationMs = (microtime(true) - $totalStart) * 1000;

        $io->definitionList(
            ['Successes' => (string) $successCount],
            ['Failures' => (string) $failureCount],
            ['Critical failures' => (string) $criticalFailures],
            ['Average latency (ms)' => number_format($averageLatencyMs, 2, '.', '')],
            ['Total duration (ms)' => number_format($durationMs, 2, '.', '')],
        );

        if ($criticalFailures > 0) {
            $io->error('At least one critical endpoint failed during warmup.');

            return Command::FAILURE;
        }

        $io->success('Public endpoints warmup completed without critical failures.');

        return Command::SUCCESS;
    }

    private function invalidateTargetedCaches(): void
    {
        $this->cacheInvalidationService->invalidatePublicPageCaches();
        $this->cacheInvalidationService->invalidatePublicPlatformListCaches();
        $this->cacheInvalidationService->invalidateBlogCaches(null);
        $this->cacheInvalidationService->invalidateApplicationListCaches();
        $this->cacheInvalidationService->invalidateShopProductListCaches();
    }

    /**
     * @return array{url: string, statusCode: int|null, success: bool, critical: bool, attempts: int, latencyMs: float}
     */
    private function warmEndpoint(string $path, bool $critical): array
    {
        $url = rtrim($this->warmupPublicEndpointsBaseUrl, '/') . $path;
        $statusCode = null;
        $success = false;
        $latencyMs = 0.0;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $startedAt = microtime(true);
            try {
                $response = $this->httpClient->request('GET', $url, [
                    'timeout' => self::REQUEST_TIMEOUT,
                    'headers' => [
                        'User-Agent' => 'warmup-bot',
                    ],
                ]);

                $statusCode = $response->getStatusCode();
                $latencyMs = (microtime(true) - $startedAt) * 1000;
                if ($statusCode >= 200 && $statusCode < 400) {
                    $success = true;

                    return [
                        'url' => $url,
                        'statusCode' => $statusCode,
                        'success' => $success,
                        'critical' => $critical,
                        'attempts' => $attempt,
                        'latencyMs' => $latencyMs,
                    ];
                }
            } catch (ExceptionInterface) {
                $latencyMs = (microtime(true) - $startedAt) * 1000;
            }
        }

        return [
            'url' => $url,
            'statusCode' => $statusCode,
            'success' => $success,
            'critical' => $critical,
            'attempts' => self::MAX_ATTEMPTS,
            'latencyMs' => $latencyMs,
        ];
    }
}
