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

use function str_contains;

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
            'key' => 'crm-sales-hub',
            'title' => 'CRM Sales Hub',
            'description' => 'Espace commercial pour suivre le pipeline et les opportunites.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-root',
            'platformReference' => 'Platform-CR-CRM 1',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000001',
                    'key' => 'application.crm.theme',
                    'value' => [
                        'theme' => 'dark',
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000001',
                    'reference' => 'Plugin-CRM-Assistant',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000001',
                            'key' => 'plugin.crm-assistant.mode',
                            'value' => [
                                'assistant' => 'sales',
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000002',
            'key' => 'crm-pipeline-pro',
            'title' => 'CRM Pipeline Pro',
            'description' => 'Vue avancee des etapes de conversion et relances.',
            'status' => PlatformStatus::MAINTENANCE,
            'private' => true,
            'ownerReference' => 'User-john-admin',
            'platformReference' => 'Platform-CR-CRM 1',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000002',
                    'key' => 'application.crm.forecast',
                    'value' => [
                        'enabled' => true,
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000002',
                    'reference' => 'Plugin-Analytics-Booster',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000002',
                            'key' => 'plugin.analytics.widgets',
                            'value' => ['pipeline', 'wonDeals'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000003',
            'key' => 'crm-support-desk',
            'title' => 'CRM Support Desk',
            'description' => 'Gestion des demandes clients et suivi de satisfaction.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-user',
            'platformReference' => 'Platform-CR-CRM 2',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000003',
                    'key' => 'application.crm.support',
                    'value' => [
                        'slaHours' => 24,
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000003',
                    'reference' => 'Plugin-Knowledge-Base-Connector',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000003',
                            'key' => 'plugin.kb.categories',
                            'value' => ['support', 'faq'],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000004',
            'key' => 'shop-ops-center',
            'title' => 'Shop Ops Center',
            'description' => 'Pilotage des operations e-commerce quotidiennes.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-root',
            'platformReference' => 'Platform-SH-Shop Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000004',
                    'key' => 'application.shop.checkout',
                    'value' => [
                        'guestCheckout' => true,
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000004',
                    'reference' => 'Plugin-Knowledge-Base-Connector',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000004',
                            'key' => 'plugin.kb.sync',
                            'value' => [
                                'intervalMinutes' => 30,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000005',
            'key' => 'shop-catalog-lab',
            'title' => 'Shop Catalog Lab',
            'description' => 'Travail sur les fiches produits et categories.',
            'status' => PlatformStatus::DISABLED,
            'private' => true,
            'ownerReference' => 'User-john-api',
            'platformReference' => 'Platform-SH-Shop Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000005',
                    'key' => 'application.shop.catalog',
                    'value' => [
                        'autoClassify' => false,
                    ],
                ],
            ],
            'plugins' => [],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000006',
            'key' => 'shop-orders-watch',
            'title' => 'Shop Orders Watch',
            'description' => 'Suivi des commandes et incidents de paiement.',
            'status' => PlatformStatus::MAINTENANCE,
            'private' => false,
            'ownerReference' => 'User-john-logged',
            'platformReference' => 'Platform-SH-Shop Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000006',
                    'key' => 'application.shop.orders',
                    'value' => [
                        'fraudScore' => true,
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000006',
                    'reference' => 'Plugin-Analytics-Booster',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000006',
                            'key' => 'plugin.analytics.orders',
                            'value' => [
                                'daily' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000007',
            'key' => 'school-campus-core',
            'title' => 'School Campus Core',
            'description' => 'Organisation des classes et planning du campus.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-root',
            'platformReference' => 'Platform-SC-School Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000007',
                    'key' => 'application.school.schedule',
                    'value' => [
                        'timezone' => 'Europe/Paris',
                    ],
                ],
            ],
            'plugins' => [],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000008',
            'key' => 'school-course-flow',
            'title' => 'School Course Flow',
            'description' => 'Flux des cours et suivi de progression pedagogique.',
            'status' => PlatformStatus::ACTIVE,
            'private' => true,
            'ownerReference' => 'User-john-admin',
            'platformReference' => 'Platform-SC-School Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000008',
                    'key' => 'application.school.course',
                    'value' => [
                        'semester' => 'S1',
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000008',
                    'reference' => 'Plugin-Private-Beta-Plugin',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000008',
                            'key' => 'plugin.beta.flags',
                            'value' => [
                                'classAssistant' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000009',
            'key' => 'school-grade-track',
            'title' => 'School Grade Track',
            'description' => 'Saisie des notes et evaluation continue.',
            'status' => PlatformStatus::MAINTENANCE,
            'private' => false,
            'ownerReference' => 'User-john-user',
            'platformReference' => 'Platform-SC-School Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000009',
                    'key' => 'application.school.grades',
                    'value' => [
                        'scale' => 20,
                    ],
                ],
            ],
            'plugins' => [],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000010',
            'key' => 'recruit-talent-hub',
            'title' => 'Recruit Talent Hub',
            'description' => 'Publication d\'offres et centralisation des talents.',
            'status' => PlatformStatus::ACTIVE,
            'private' => false,
            'ownerReference' => 'User-john-root',
            'platformReference' => 'Platform-RE-Recruit Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000010',
                    'key' => 'application.recruit.visibility',
                    'value' => [
                        'publicJobs' => true,
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000010',
                    'reference' => 'Plugin-CRM-Assistant',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000010',
                            'key' => 'plugin.crm-assistant.recruit',
                            'value' => [
                                'rankingHelp' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000011',
            'key' => 'recruit-hiring-pipeline',
            'title' => 'Recruit Hiring Pipeline',
            'description' => 'Pipeline de recrutement avec etapes de qualification.',
            'status' => PlatformStatus::ACTIVE,
            'private' => true,
            'ownerReference' => 'User-john-admin',
            'platformReference' => 'Platform-RE-Recruit Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000011',
                    'key' => 'application.recruit.pipeline',
                    'value' => [
                        'stages' => ['screening', 'interview'],
                    ],
                ],
            ],
            'plugins' => [
                [
                    'uuid' => '62000000-0000-1000-8000-000000000011',
                    'reference' => 'Plugin-Private-Beta-Plugin',
                    'configurations' => [
                        [
                            'uuid' => '63000000-0000-1000-8000-000000000011',
                            'key' => 'plugin.beta.recruit',
                            'value' => [
                                'cvParsingV2' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
        [
            'uuid' => '60000000-0000-1000-8000-000000000012',
            'key' => 'recruit-interview-desk',
            'title' => 'Recruit Interview Desk',
            'description' => 'Gestion des entretiens et feedbacks candidats.',
            'status' => PlatformStatus::DISABLED,
            'private' => false,
            'ownerReference' => 'User-john-user',
            'platformReference' => 'Platform-RE-Recruit Principal',
            'appConfigurations' => [
                [
                    'uuid' => '61000000-0000-1000-8000-000000000012',
                    'key' => 'application.recruit.interview',
                    'value' => [
                        'calendarSync' => false,
                    ],
                ],
            ],
            'plugins' => [],
        ],
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

            if (str_contains($item['platformReference'], 'Platform-RE-')) {
                $this->addReference('Recruit-Application-' . $item['key'], $application);

                // Backward compatibility for older recruit fixtures expecting this reference key.
                if ($item['key'] === 'recruit-talent-hub') {
                    $this->addReference('Application-recruit-lite-app', $application);
                }
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 7;
    }
}
