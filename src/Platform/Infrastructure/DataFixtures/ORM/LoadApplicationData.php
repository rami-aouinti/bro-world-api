<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\DataFixtures\ORM;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\General\Domain\Rest\UuidHelper;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Entity\Plugin;
use App\Platform\Domain\Enum\PlatformStatus;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

/**
 * @package App\Platform
 */
final class LoadApplicationData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<int, array{
     *     uuid: non-empty-string,
     *     key: non-empty-string,
     *     title: non-empty-string,
     *     status: PlatformStatus,
     *     platformReference: non-empty-string,
     *     appConfigurations: array<int, array{uuid: non-empty-string, key: non-empty-string, value: array<string, mixed>}>,
     *     plugins: array<int, array{uuid: non-empty-string, reference: non-empty-string, configurations: array<int, array{uuid: non-empty-string, key: non-empty-string, value: array<string, mixed>}>}>
     * }
     */
    private const array DATA = [
        [
            'uuid' => '60000000-0000-1000-8000-000000000001',
            'key' => 'crm-growth-app',
            'title' => 'CRM Growth App',
            'status' => PlatformStatus::ACTIVE,
            'platformReference' => 'Platform-CR-CRM 1',
            'appConfigurations' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000101',
                    'key' => 'application.crm.theme',
                    'value' => ['theme' => 'dark', 'density' => 'compact'],
                ],
                [
                    'uuid' => '60000000-0000-1000-8000-000000000102',
                    'key' => 'application.crm.access',
                    'value' => ['regions' => ['eu', 'us'], 'strictMode' => true],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000201',
                    'reference' => 'Plugin-CRM-Assistant',
                    'configurations' => [
                        [
                            'uuid' => '60000000-0000-1000-8000-000000000301',
                            'key' => 'plugin.crm-assistant.prompts',
                            'value' => ['autoSummary' => true, 'language' => 'en'],
                        ],
                    ],
                ],
                [
                    'uuid' => '60000000-0000-1000-8000-000000000202',
                    'reference' => 'Plugin-Analytics-Booster',
                    'configurations' => [
                        [
                            'uuid' => '60000000-0000-1000-8000-000000000302',
                            'key' => 'plugin.analytics.widgets',
                            'value' => ['enabled' => ['pipeline', 'conversion']],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000002',
            'key' => 'shop-ops-app',
            'title' => 'Shop Ops App',
            'status' => PlatformStatus::MAINTENANCE,
            'platformReference' => 'Platform-SH-Shop Principal',
            'appConfigurations' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000103',
                    'key' => 'application.shop.checkout',
                    'value' => ['retryPayment' => true, 'guestCheckout' => false],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000203',
                    'reference' => 'Plugin-Knowledge-Base-Connector',
                    'configurations' => [
                        [
                            'uuid' => '60000000-0000-1000-8000-000000000303',
                            'key' => 'plugin.kb.sync',
                            'value' => ['intervalMinutes' => 15, 'categories' => ['faq', 'refund']],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000003',
            'key' => 'recruit-lite-app',
            'title' => 'Recruit Lite App',
            'status' => PlatformStatus::DISABLED,
            'platformReference' => 'Platform-RE-Recruit Principal',
            'appConfigurations' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000104',
                    'key' => 'application.recruit.visibility',
                    'value' => ['publicJobs' => false, 'teamOnly' => true],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000204',
                    'reference' => 'Plugin-Private-Beta-Plugin',
                    'configurations' => [
                        [
                            'uuid' => '60000000-0000-1000-8000-000000000304',
                            'key' => 'plugin.beta.flags',
                            'value' => ['aiRanking' => false, 'cvParsingV2' => true],
                        ],
                    ],
                ],
            ],
        ],
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        /** @var User $owner */
        $owner = $this->getReference('User-john-root', User::class);

        foreach (self::DATA as $item) {
            /** @var Platform $platform */
            $platform = $this->getReference($item['platformReference'], Platform::class);

            $application = (new Application())
                ->setUser($owner)
                ->setPlatform($platform)
                ->setTitle($item['title'])
                ->setStatus($item['status']);

            PhpUnitUtil::setProperty('id', UuidHelper::fromString($item['uuid']), $application);

            foreach ($item['appConfigurations'] as $appConfigurationData) {
                $configuration = (new Configuration())
                    ->setConfigurationKey($appConfigurationData['key'])
                    ->setConfigurationValue($appConfigurationData['value'])
                    ->setScope(ConfigurationScope::PLATFORM)
                    ->setPrivate(true)
                    ->setApplication($application);

                PhpUnitUtil::setProperty('id', UuidHelper::fromString($appConfigurationData['uuid']), $configuration);

                $application->addConfiguration($configuration);
                $manager->persist($configuration);
            }

            foreach ($item['plugins'] as $pluginData) {
                /** @var Plugin $plugin */
                $plugin = $this->getReference($pluginData['reference'], Plugin::class);

                $applicationPlugin = (new ApplicationPlugin())
                    ->setApplication($application)
                    ->setPlugin($plugin);

                PhpUnitUtil::setProperty('id', UuidHelper::fromString($pluginData['uuid']), $applicationPlugin);

                foreach ($pluginData['configurations'] as $pluginConfigurationData) {
                    $pluginConfiguration = (new Configuration())
                        ->setConfigurationKey($pluginConfigurationData['key'])
                        ->setConfigurationValue($pluginConfigurationData['value'])
                        ->setScope(ConfigurationScope::PLUGIN)
                        ->setPrivate(true)
                        ->setApplicationPlugin($applicationPlugin);

                    PhpUnitUtil::setProperty('id', UuidHelper::fromString($pluginConfigurationData['uuid']), $pluginConfiguration);

                    $applicationPlugin->addConfiguration($pluginConfiguration);
                    $manager->persist($pluginConfiguration);
                }

                $application->addApplicationPlugin($applicationPlugin);
                $manager->persist($applicationPlugin);
            }

            $manager->persist($application);
            $this->addReference('Application-' . $item['key'], $application);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 7;
    }
}
