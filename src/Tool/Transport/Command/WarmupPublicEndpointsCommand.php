<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command;

use App\General\Application\Service\CacheInvalidationService;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use App\Tool\Application\DTO\Warmup\WarmupEndpointConfig;
use App\Tool\Application\Service\Elastic\Interfaces\ReindexAllDomainsServiceInterface;
use App\Tool\Application\Service\Warmup\WarmupPublicEndpointsConfigProvider;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

use function array_chunk;
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

    public function __construct(
        private readonly CacheInvalidationService $cacheInvalidationService,
        private readonly ReindexAllDomainsServiceInterface $reindexAllDomainsService,
        private readonly HttpClientInterface $httpClient,
        private readonly WarmupPublicEndpointsConfigProvider $configProvider,
        private readonly string $warmupPublicEndpointsBaseUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);
        $totalStart = microtime(true);

        $config = $this->configProvider->getConfig();

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
        $results = $this->warmEndpoints($config->endpoints, $config->maxConcurrency, $config->timeoutSeconds, $config->retryMax, $config->successThresholdMs);
        foreach ($results as $result) {
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
     * @param list<WarmupEndpointConfig> $endpoints
     *
     * @return list<array{url: string, statusCode: int|null, success: bool, critical: bool, attempts: int, latencyMs: float}>
     */
    private function warmEndpoints(array $endpoints, int $maxConcurrency, float $timeoutSeconds, int $retryMax, ?float $globalSuccessThresholdMs): array
    {
        $resultsByPath = [];

        for ($attempt = 1; $attempt <= $retryMax; ++$attempt) {
            $remaining = [];
            foreach ($endpoints as $endpoint) {
                if (!isset($resultsByPath[$endpoint->path]) || !$resultsByPath[$endpoint->path]['success']) {
                    $remaining[] = $endpoint;
                }
            }

            if ($remaining === []) {
                break;
            }

            foreach (array_chunk($remaining, $maxConcurrency) as $chunk) {
                $responses = [];
                foreach ($chunk as $endpoint) {
                    $url = rtrim($this->warmupPublicEndpointsBaseUrl, '/') . $endpoint->path;
                    $responses[$endpoint->path] = [
                        'endpoint' => $endpoint,
                        'url' => $url,
                        'startedAt' => microtime(true),
                        'response' => $this->httpClient->request('GET', $url, [
                            'timeout' => $timeoutSeconds,
                            'headers' => [
                                'User-Agent' => 'warmup-bot',
                            ],
                        ]),
                    ];
                }

                foreach ($responses as $path => $responseData) {
                    $endpoint = $responseData['endpoint'];
                    $statusCode = null;
                    $success = false;
                    $latencyMs = 0.0;

                    try {
                        $response = $responseData['response'];
                        if ($response instanceof ResponseInterface) {
                            $statusCode = $response->getStatusCode();
                        }
                        $latencyMs = (microtime(true) - $responseData['startedAt']) * 1000;
                        $successThresholdMs = $endpoint->successThresholdMs ?? $globalSuccessThresholdMs;

                        $success = $statusCode !== null
                            && $statusCode >= 200
                            && $statusCode < 400
                            && ($successThresholdMs === null || $latencyMs <= $successThresholdMs);
                    } catch (ExceptionInterface) {
                        $latencyMs = (microtime(true) - $responseData['startedAt']) * 1000;
                    }

                    $resultsByPath[$path] = [
                        'url' => $responseData['url'],
                        'statusCode' => $statusCode,
                        'success' => $success,
                        'critical' => $endpoint->critical,
                        'attempts' => $attempt,
                        'latencyMs' => $latencyMs,
                    ];
                }
            }
        }

        $orderedResults = [];
        foreach ($endpoints as $endpoint) {
            $orderedResults[] = $resultsByPath[$endpoint->path] ?? [
                'url' => rtrim($this->warmupPublicEndpointsBaseUrl, '/') . $endpoint->path,
                'statusCode' => null,
                'success' => false,
                'critical' => $endpoint->critical,
                'attempts' => $retryMax,
                'latencyMs' => 0.0,
            ];
        }

        return $orderedResults;
    }
}
