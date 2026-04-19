<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\Configuration\Domain\Entity\Configuration;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Repository\Interfaces\ApplicationRepositoryInterface;

readonly class PublicGeneralApplicationCatalogService
{
    public function __construct(
        private ApplicationRepositoryInterface $applicationRepository,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCatalog(): array
    {
        $items = [];

        foreach ($this->applicationRepository->findPublicGeneralApplications() as $application) {
            $items[] = $this->normalizeApplication($application);
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeApplication(Application $application): array
    {
        return [
            'id' => $application->getId(),
            'title' => $application->getTitle(),
            'slug' => $application->getSlug(),
            'description' => $application->getDescription(),
            'photo' => $application->getPhoto(),
            'status' => $application->getStatus()->value,
            'platform' => [
                'id' => $application->getPlatform()?->getId(),
                'name' => $application->getPlatform()?->getName(),
                'key' => $application->getPlatform()?->getPlatformKeyValue(),
                'description' => $application->getPlatform()?->getDescription(),
            ],
            'configurations' => array_map(
                static fn (Configuration $configuration): array => [
                    'id' => $configuration->getId(),
                    'key' => $configuration->getConfigurationKey(),
                    'value' => $configuration->getConfigurationValue(),
                ],
                $application->getConfigurations()->toArray(),
            ),
            'plugins' => array_map(
                static fn (ApplicationPlugin $applicationPlugin): array => [
                    'id' => $applicationPlugin->getId(),
                    'name' => $applicationPlugin->getPlugin()?->getName(),
                    'key' => $applicationPlugin->getPlugin()?->getPluginKeyValue(),
                    'description' => $applicationPlugin->getPlugin()?->getDescription(),
                    'configurations' => array_map(
                        static fn (Configuration $configuration): array => [
                            'id' => $configuration->getId(),
                            'key' => $configuration->getConfigurationKey(),
                            'value' => $configuration->getConfigurationValue(),
                        ],
                        $applicationPlugin->getConfigurations()->toArray(),
                    ),
                ],
                $application->getApplicationPlugins()->toArray(),
            ),
        ];
    }
}
