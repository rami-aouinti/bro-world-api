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
     *     description: non-empty-string,
     *     status: PlatformStatus,
     *     private: bool,
     *     ownerReference: non-empty-string,
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
            'description' => 'Application CRM pour la croissance commerciale.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-root',
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
            'description' => 'Application de gestion des operations e-commerce.',
            'status' => PlatformStatus::MAINTENANCE,
            'private' => false,
            'ownerReference' => 'User-john-root',
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
            'description' => 'Application privee pour le recrutement interne.',
            'status' => PlatformStatus::DISABLED,
            'private' => true,
            'ownerReference' => 'User-john-root',
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
        [
            'uuid' => '60000000-0000-1000-8000-000000000004',
            'key' => 'john-user-private-app',
            'title' => 'John User Private App',
            'description' => 'Application privee de l\'utilisateur authentifie john-user.',
            'status' => PlatformStatus::ACTIVE,
            'private' => true,
            'ownerReference' => 'User-john-user',
            'platformReference' => 'Platform-CR-CRM 1',
            'appConfigurations' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000105',
                    'key' => 'application.john-user.notifications',
                    'value' => ['email' => true, 'push' => false],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '60000000-0000-1000-8000-000000000205',
                    'reference' => 'Plugin-CRM-Assistant',
                    'configurations' => [
                        [
                            'uuid' => '60000000-0000-1000-8000-000000000305',
                            'key' => 'plugin.crm-assistant.private-mode',
                            'value' => ['enabled' => true],
                        ],
                    ],
                ],
            ],
        ]
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $item) {
            /** @var User $owner */
            $owner = $this->getReference($item['ownerReference'], User::class);

            /** @var Platform $platform */
            $platform = $this->getReference($item['platformReference'], Platform::class);

            $application = (new Application())
                ->setUser($owner)
                ->setPlatform($platform)
                ->setTitle($item['title'])
                ->setDescription($item['description'])
                ->setStatus($item['status'])
                ->setPrivate($item['private']);

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
