<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\DataFixtures\ORM;

use App\General\Domain\Rest\UuidHelper;
use App\Platform\Domain\Entity\Plugin;
use App\Tests\Utils\PhpUnitUtil;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

/**
 * @package App\Platform
 */
final class LoadPluginData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'CRM Assistant' => '50000000-0000-1000-8000-000000000001',
        'Analytics Booster' => '50000000-0000-1000-8000-000000000002',
        'Private Beta Plugin' => '50000000-0000-1000-8000-000000000003',
        'Disabled Public Plugin' => '50000000-0000-1000-8000-000000000004',
        'Knowledge Base Connector' => '50000000-0000-1000-8000-000000000005',
    ];

    /**
     * @var array<int, array{uuid: non-empty-string, key: non-empty-string, name: non-empty-string, enabled: bool, private: bool, description: non-empty-string}>
     */
    private const array DATA = [
        [
            'uuid' => '50000000-0000-1000-8000-000000000001',
            'key' => 'CRM-Assistant',
            'name' => 'CRM Assistant',
            'enabled' => true,
            'private' => false,
            'description' => 'AI assistant to summarize conversations, suggest follow-ups, and improve CRM workflows.',
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000002',
            'key' => 'Analytics-Booster',
            'name' => 'Analytics Booster',
            'enabled' => true,
            'private' => false,
            'description' => 'Advanced analytics plugin with dashboards, KPIs, and custom reporting blocks.',
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000003',
            'key' => 'Private-Beta-Plugin',
            'name' => 'Private Beta Plugin',
            'enabled' => true,
            'private' => true,
            'description' => 'Experimental plugin available only for selected internal beta users.',
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000004',
            'key' => 'Disabled-Public-Plugin',
            'name' => 'Disabled Public Plugin',
            'enabled' => false,
            'private' => false,
            'description' => 'Legacy plugin currently disabled during migration and performance checks.',
        ],
        [
            'uuid' => '50000000-0000-1000-8000-000000000005',
            'key' => 'Knowledge-Base-Connector',
            'name' => 'Knowledge Base Connector',
            'enabled' => true,
            'private' => false,
            'description' => 'Connector to sync articles and FAQs from external knowledge base systems.',
        ],
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $item) {
            $entity = new Plugin()
                ->setName($item['name'])
                ->setDescription($item['description'])
                ->setEnabled($item['enabled'])
                ->setPrivate($item['private'])
                ->ensureGeneratedPhoto();

            PhpUnitUtil::setProperty('id', UuidHelper::fromString($item['uuid']), $entity);

            $manager->persist($entity);
            $this->addReference('Plugin-' . $item['key'], $entity);
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
