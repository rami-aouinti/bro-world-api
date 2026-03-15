<?php

declare(strict_types=1);

namespace App\Shop\Application\Monitoring;

use Psr\Log\LoggerInterface;

final readonly class ShopMonitoringService
{
    public function __construct(
        private LoggerInterface $logger,
        private LoggerInterface $monitoringLogger,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function logStructured(string $event, string $message, array $context = [], string $level = 'warning'): void
    {
        $payload = [
            'event' => $event,
            ...$context,
        ];

        match ($level) {
            'critical' => $this->logger->critical($message, $payload),
            'error' => $this->logger->error($message, $payload),
            'info' => $this->logger->info($message, $payload),
            default => $this->logger->warning($message, $payload),
        };
    }

    /**
     * @param array<string, scalar|null> $labels
     */
    public function incrementCounter(string $name, array $labels = []): void
    {
        $this->monitoringLogger->info('metric.counter.increment', [
            'metric' => $name,
            'value' => 1,
            'labels' => $labels,
        ]);
    }
}
