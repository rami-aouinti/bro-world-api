<?php

declare(strict_types=1);

namespace App\Platform\Infrastructure\DataFixtures\ORM;

use App\General\Domain\Rest\UuidHelper;
use App\Platform\Domain\Entity\Platform;
use App\Tests\Utils\PhpUnitUtil;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

/**
 * @package App\Platform
 */
final class LoadPlatformData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<int, array{uuid: non-empty-string, code: non-empty-string, platformKey: non-empty-string, name: non-empty-string, enabled: bool, description: non-empty-string}>
     */
    private const array DATA = [
        [
            'uuid' => '40000000-0000-1000-8000-000000000001',
            'code' => 'CR',
            'platformKey' => 'crm',
            'name' => 'CRM 1',
            'enabled' => true,
            'description' => 'Complete CRM module to centralize customer relationships, track opportunities, and structure commercial activity.',
        ],
        [
            'uuid' => '40000000-0000-1000-8000-000000000002',
            'code' => 'CR',
            'platformKey' => 'crm',
            'name' => 'CRM 2',
            'enabled' => true,
            'description' => 'Complete CRM module to centralize customer relationships, track opportunities, and structure commercial activity.',
        ],
        [
            'uuid' => '40000000-0000-1000-8000-000000000003',
            'code' => 'SH',
            'platformKey' => 'shop',
            'name' => 'Shop Principal',
            'enabled' => true,
            'description' => 'E-commerce suite to manage product catalogs, orders, payments, and sales performance.',
        ],
        [
            'uuid' => '40000000-0000-1000-8000-000000000004',
            'code' => 'RE',
            'platformKey' => 'recruit',
            'name' => 'Recruit Principal',
            'enabled' => true,
            'description' => 'Recruitment workspace to publish job offers, qualify applications, and manage interviews efficiently.',
        ],
        [
            'uuid' => '40000000-0000-1000-8000-000000000005',
            'code' => 'SC',
            'platformKey' => 'school',
            'name' => 'School Principal',
            'enabled' => true,
            'description' => 'School module to organize classes, plan lessons, and monitor learner progress.',
        ],
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $item) {
            $entity = new Platform()
                ->setName($item['name'])
                ->setDescription($item['description'])
                ->setPlatformKey($item['platformKey'])
                ->setEnabled($item['enabled'])
                ->setPrivate(false)
                ->ensureGeneratedPhoto();

            PhpUnitUtil::setProperty('id', UuidHelper::fromString($item['uuid']), $entity);

            $manager->persist($entity);
            $this->addReference('Platform-' . $item['code'] . '-' . $item['name'], $entity);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 5;
    }
}
