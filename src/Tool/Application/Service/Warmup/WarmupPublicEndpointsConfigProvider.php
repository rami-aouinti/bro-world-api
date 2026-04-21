<?php

declare(strict_types=1);

namespace App\Tool\Application\Service\Warmup;

use App\Tool\Application\DTO\Warmup\WarmupEndpointConfig;
use App\Tool\Application\DTO\Warmup\WarmupPublicEndpointsConfig;
use InvalidArgumentException;
use Symfony\Component\Yaml\Yaml;

use function array_key_exists;
use function is_array;
use function is_int;
use function is_numeric;
use function is_string;

final class WarmupPublicEndpointsConfigProvider
{
    public function __construct(private readonly string $configPath)
    {
    }

    public function getConfig(): WarmupPublicEndpointsConfig
    {
        $rawConfig = Yaml::parseFile($this->configPath);
        if (!is_array($rawConfig)) {
            throw new InvalidArgumentException('Warmup public endpoints config must be a YAML map.');
        }

        $criticalEndpoints = $this->parseEndpointList($rawConfig, 'critical', true);
        $secondaryEndpoints = $this->parseEndpointList($rawConfig, 'secondary', false);

        return new WarmupPublicEndpointsConfig(
            endpoints: [...$criticalEndpoints, ...$secondaryEndpoints],
            maxConcurrency: $this->readPositiveInt($rawConfig, 'max_concurrency'),
            timeoutSeconds: $this->readPositiveFloat($rawConfig, 'timeout_seconds'),
            retryMax: $this->readPositiveInt($rawConfig, 'retry_max'),
            successThresholdMs: $this->readOptionalPositiveFloat($rawConfig, 'success_threshold_ms'),
        );
    }

    /**
     * @param array<string, mixed> $rawConfig
     *
     * @return list<WarmupEndpointConfig>
     */
    private function parseEndpointList(array $rawConfig, string $key, bool $critical): array
    {
        $value = $rawConfig[$key] ?? null;
        if (!is_array($value)) {
            throw new InvalidArgumentException(sprintf('Warmup config key "%s" must be a list.', $key));
        }

        $endpoints = [];
        foreach ($value as $index => $endpointData) {
            if (!is_array($endpointData)) {
                throw new InvalidArgumentException(sprintf('Warmup endpoint "%s[%d]" must be an object.', $key, $index));
            }

            $path = $endpointData['path'] ?? null;
            if (!is_string($path) || $path === '') {
                throw new InvalidArgumentException(sprintf('Warmup endpoint "%s[%d].path" must be a non-empty string.', $key, $index));
            }

            $successThresholdMs = $this->readOptionalPositiveFloat($endpointData, 'success_threshold_ms');

            $endpoints[] = new WarmupEndpointConfig(
                path: $path,
                critical: $critical,
                successThresholdMs: $successThresholdMs,
            );
        }

        return $endpoints;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readPositiveInt(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw new InvalidArgumentException(sprintf('Warmup config key "%s" must be a positive integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readPositiveFloat(array $data, string $key): float
    {
        $value = $data[$key] ?? null;
        if (!is_numeric($value) || (float) $value <= 0.0) {
            throw new InvalidArgumentException(sprintf('Warmup config key "%s" must be a positive number.', $key));
        }

        return (float) $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function readOptionalPositiveFloat(array $data, string $key): ?float
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }

        $value = $data[$key];
        if (!is_numeric($value) || (float) $value <= 0.0) {
            throw new InvalidArgumentException(sprintf('Warmup config key "%s" must be a positive number when provided.', $key));
        }

        return (float) $value;
    }
}
