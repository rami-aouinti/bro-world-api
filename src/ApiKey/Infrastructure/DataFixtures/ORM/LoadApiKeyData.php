<?php

declare(strict_types=1);

namespace App\ApiKey\Infrastructure\DataFixtures\ORM;

use App\ApiKey\Domain\Entity\ApiKey;
use App\General\Domain\Rest\UuidHelper;
use App\Role\Application\Security\Interfaces\RolesServiceInterface;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\UserGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

use function array_map;
use function str_pad;

/**
 * @package App\ApiKey
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LoadApiKeyData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<string, non-empty-string>
     */
    public static array $uuids = [
        '' => '30000000-0000-1000-8000-000000000001',
        '-logged' => '30000000-0000-1000-8000-000000000002',
        '-api' => '30000000-0000-1000-8000-000000000003',
        '-user' => '30000000-0000-1000-8000-000000000004',
        '-admin' => '30000000-0000-1000-8000-000000000005',
        '-root' => '30000000-0000-1000-8000-000000000006',
        '-recruiter' => '30000000-0000-1000-8000-000000000007',
        '-hiring_manager' => '30000000-0000-1000-8000-000000000008',
        '-interviewer' => '30000000-0000-1000-8000-000000000009',
        '-crm_owner' => '30000000-0000-1000-8000-000000000010',
        '-crm_admin' => '30000000-0000-1000-8000-000000000011',
        '-crm_manager' => '30000000-0000-1000-8000-000000000012',
        '-crm_sales' => '30000000-0000-1000-8000-000000000013',
        '-crm_support' => '30000000-0000-1000-8000-000000000014',
        '-crm_marketing' => '30000000-0000-1000-8000-000000000015',
        '-crm_viewer' => '30000000-0000-1000-8000-000000000016',
        '-shop_viewer' => '30000000-0000-1000-8000-000000000017',
        '-shop_editor' => '30000000-0000-1000-8000-000000000018',
        '-shop_manager' => '30000000-0000-1000-8000-000000000019',
        '-school_viewer' => '30000000-0000-1000-8000-000000000020',
        '-school_editor' => '30000000-0000-1000-8000-000000000021',
        '-school_manager' => '30000000-0000-1000-8000-000000000022',
        '-job_viewer' => '30000000-0000-1000-8000-000000000023',
        '-job_editor' => '30000000-0000-1000-8000-000000000024',
        '-job_manager' => '30000000-0000-1000-8000-000000000025',
    ];

    public function __construct(
        private readonly RolesServiceInterface $rolesService,
    ) {
    }

    /**
     * Load data fixtures with the passed EntityManager
     *
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        // Create entities
        array_map(
            fn (?string $role): bool => $this->createApiKey($manager, $role),
            [
                null,
                ...$this->rolesService->getRoles(),
            ],
        );

        // Flush database changes
        $manager->flush();
    }

    /**
     * Get the order of this fixture
     */
    #[Override]
    public function getOrder(): int
    {
        return 4;
    }

    public static function getUuidByKey(string $key): string
    {
        return self::$uuids[$key];
    }

    /**
     * Helper method to create new ApiKey entity with specified role.
     *
     * @throws Throwable
     */
    private function createApiKey(ObjectManager $manager, ?string $role = null): true
    {
        // Create new entity
        $entity = new ApiKey()
            ->setDescription('ApiKey Description: ' . ($role === null ? '' : $this->rolesService->getShort($role)))
            ->setToken(str_pad($role === null ? '' : $this->rolesService->getShort($role), ApiKey::TOKEN_LENGTH, '_'));
        $suffix = '';

        if ($role !== null) {
            /** @var UserGroup $userGroup */
            $userGroup = $this->getReference('UserGroup-' . $this->rolesService->getShort($role), UserGroup::class);
            $entity->addUserGroup($userGroup);
            $suffix = '-' . $this->rolesService->getShort($role);
        }

        PhpUnitUtil::setProperty(
            'id',
            UuidHelper::fromString(self::$uuids[$suffix]),
            $entity
        );

        // Persist entity
        $manager->persist($entity);
        // Create reference for later usage
        $this->addReference('ApiKey' . $suffix, $entity);

        return true;
    }
}
