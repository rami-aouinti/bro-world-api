<?php

declare(strict_types=1);

namespace App\Tool\Application\Service\Warmup;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function bin2hex;
use function microtime;
use function parse_url;
use function random_bytes;
use function sprintf;

final readonly class WarmupPublicEndpointsObservabilityService
{
    private const string CRITICAL_FAILURE_COUNTER_KEY = 'warmup:public_endpoints:critical_consecutive_failures';

    public function __construct(
        private LoggerInterface $monitoringLogger,
        private CacheItemPoolInterface $appCache,
        private HttpClientInterface $httpClient,
        private ?NotifierInterface $notifier,
        private int $warmupCriticalFailureWindowSeconds,
        private int $warmupCriticalFailureAlertThreshold,
        private string $warmupCriticalFailureSlackWebhook,
    ) {
    }

    public function createRunId(): string
    {
        return sprintf('warmup-%d-%s', (int) (microtime(true) * 1000), bin2hex(random_bytes(4)));
    }

    public function logAttempt(
        string $runId,
        string $endpoint,
        string $group,
        int $attempt,
        ?int $statusCode,
        float $durationMs,
        string $result,
    ): void {
        $this->monitoringLogger->info('warmup.public_endpoints.attempt', [
            'run_id' => $runId,
            'endpoint' => $this->sanitizeEndpoint($endpoint),
            'group' => $group,
            'attempt' => $attempt,
            'status_code' => $statusCode,
            'duration_ms' => (int) round($durationMs),
            'result' => $result,
        ]);
    }

    public function logRunCompleted(
        string $runId,
        int $criticalOk,
        int $criticalFailed,
        int $secondaryFailed,
        float $totalDurationMs,
    ): void {
        $this->monitoringLogger->info('warmup.public_endpoints.run_completed', [
            'run_id' => $runId,
            'critical_ok' => $criticalOk,
            'critical_failed' => $criticalFailed,
            'secondary_failed' => $secondaryFailed,
            'total_duration_ms' => (int) round($totalDurationMs),
        ]);
    }

    public function trackCriticalFailuresAndAlert(string $runId, int $criticalFailed): void
    {
        if ($criticalFailed <= 0) {
            $this->appCache->deleteItem(self::CRITICAL_FAILURE_COUNTER_KEY);

            return;
        }

        $counterItem = $this->appCache->getItem(self::CRITICAL_FAILURE_COUNTER_KEY);
        $current = $counterItem->isHit() ? (int) $counterItem->get() : 0;
        $newValue = $current + 1;

        $counterItem->set($newValue);
        $counterItem->expiresAfter($this->warmupCriticalFailureWindowSeconds);
        $this->appCache->save($counterItem);

        if ($newValue !== $this->warmupCriticalFailureAlertThreshold) {
            return;
        }

        $message = sprintf(
            'Warmup public endpoints: %d consecutive runs have critical failures (run_id=%s).',
            $newValue,
            $runId,
        );

        $this->monitoringLogger->critical('warmup.public_endpoints.consecutive_critical_failures', [
            'run_id' => $runId,
            'consecutive_failures' => $newValue,
            'window_seconds' => $this->warmupCriticalFailureWindowSeconds,
            'threshold' => $this->warmupCriticalFailureAlertThreshold,
        ]);

        $this->sendAlert($message);
    }

    private function sendAlert(string $message): void
    {
        if ($this->warmupCriticalFailureSlackWebhook !== '') {
            try {
                $this->httpClient->request('POST', $this->warmupCriticalFailureSlackWebhook, [
                    'json' => [
                        'text' => $message,
                    ],
                ]);

                return;
            } catch (ExceptionInterface) {
                $this->monitoringLogger->warning('warmup.public_endpoints.alert_slack_failed');
            }
        }

        if ($this->notifier === null) {
            return;
        }

        $notification = new Notification($message);
        $notification->importance(Notification::IMPORTANCE_HIGH);

        $this->notifier->send($notification);
    }

    private function sanitizeEndpoint(string $endpoint): string
    {
        $path = parse_url($endpoint, \PHP_URL_PATH);

        return $path !== false ? $path : $endpoint;
    }
}
