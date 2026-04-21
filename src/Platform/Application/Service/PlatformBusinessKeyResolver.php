<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Enum\PlatformKey;

use function explode;
use function in_array;
use function strtolower;

final class PlatformBusinessKeyResolver
{
    /**
     * @var array<string, PlatformKey>
     */
    private const array ORCHESTRATION_ALIAS_MAP = [
        'learning' => PlatformKey::SCHOOL,
        'job' => PlatformKey::RECRUIT,
    ];

    public function resolve(Application $application): ?PlatformKey
    {
        $platformKey = $application->getPlatform()?->getPlatformKey();
        if (in_array($platformKey, [PlatformKey::SCHOOL, PlatformKey::RECRUIT], true)) {
            return $platformKey;
        }

        $resolvedFromConfigurations = $this->resolveFromApplicationConfigurations($application);
        if ($resolvedFromConfigurations instanceof PlatformKey) {
            return $resolvedFromConfigurations;
        }

        return $platformKey;
    }

    private function resolveFromApplicationConfigurations(Application $application): ?PlatformKey
    {
        foreach ($application->getConfigurations() as $configuration) {
            $resolved = $this->resolveFromConfigurationKey($configuration->getConfigurationKey());
            if ($resolved instanceof PlatformKey) {
                return $resolved;
            }
        }

        foreach ($application->getApplicationPlugins() as $applicationPlugin) {
            if (!$applicationPlugin instanceof ApplicationPlugin) {
                continue;
            }

            foreach ($applicationPlugin->getConfigurations() as $configuration) {
                $resolved = $this->resolveFromConfigurationKey($configuration->getConfigurationKey());
                if ($resolved instanceof PlatformKey) {
                    return $resolved;
                }
            }
        }

        return null;
    }

    private function resolveFromConfigurationKey(string $configurationKey): ?PlatformKey
    {
        $segments = explode('.', $configurationKey);
        $alias = strtolower((string)end($segments));

        if ($alias === '') {
            return null;
        }

        return self::ORCHESTRATION_ALIAS_MAP[$alias] ?? null;
    }
}
