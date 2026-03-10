<?php

declare(strict_types=1);

namespace App\Configuration\Infrastructure\DataFixtures\ORM;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\General\Domain\Rest\UuidHelper;
use App\Tests\Utils\PhpUnitUtil;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

/**
 * @package App\Configuration
 */
final class LoadConfigurationData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<int, array{uuid: non-empty-string, key: non-empty-string, value: array<string, mixed>, scope: ConfigurationScope, private: bool}>
     */
    private const array DATA = [
        [
            'uuid' => '50000000-0000-1000-8000-000000000001',
            'key' => 'system.default.locale',
            'value' => [
                'locale' => 'en',
            ],
            'scope' => ConfigurationScope::SYSTEM,
            'private' => false,
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000002',
            'key' => 'public.branding',
            'value' => [
                'title' => 'BRO World',
                'theme' => 'dark',
            ],
            'scope' => ConfigurationScope::PUBLIC,
            'private' => false,
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000003',
            'key' => 'plugin.crm.toggles',
            'value' => [
                'featureA' => true,
                'featureB' => false,
            ],
            'scope' => ConfigurationScope::PLUGIN,
            'private' => false,
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000004',
            'key' => 'user.dashboard.preferences',
            'value' => [
                'layout' => 'compact',
                'cards' => ['sales', 'tasks'],
            ],
            'scope' => ConfigurationScope::USER,
            'private' => false,
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000005',
            'key' => 'platform.secrets',
            'value' => [
                'apiSecret' => 'secret-value',
                'rotation' => 30,
            ],
            'scope' => ConfigurationScope::PLATFORM,
            'private' => true,
        ],
    ];
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'system-default-locale' => '50000000-0000-1000-8000-000000000001',
        'public-branding' => '50000000-0000-1000-8000-000000000002',
        'plugin-crm-toggles' => '50000000-0000-1000-8000-000000000003',
        'user-dashboard-preferences' => '50000000-0000-1000-8000-000000000004',
        'platform-secrets' => '50000000-0000-1000-8000-000000000005',
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $item) {
            $entity = new Configuration();
            $entity
                ->setConfigurationKey($item['key'])
                ->setConfigurationValue($item['value'])
                ->setScope($item['scope'])
                ->setPrivate($item['private']);

            PhpUnitUtil::setProperty('id', UuidHelper::fromString($item['uuid']), $entity);

            $manager->persist($entity);
            $this->addReference('Configuration-' . $item['key'], $entity);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 6;
    }

    public static function getUuidByKey(string $key): string
    {
        return self::$uuids[$key];
    }
}
