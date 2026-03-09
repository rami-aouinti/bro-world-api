<?php

declare(strict_types=1);

namespace App\User\Infrastructure\DataFixtures\ORM;

use App\General\Domain\Enum\Language;
use App\General\Domain\Enum\Locale;
use App\General\Domain\Rest\UuidHelper;
use App\Role\Application\Security\Interfaces\RolesServiceInterface;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserGroup;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

use function array_map;

/**
 * @package App\User
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
final class LoadUserData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'john' => '20000000-0000-1000-8000-000000000001',
        'john-logged' => '20000000-0000-1000-8000-000000000002',
        'john-api' => '20000000-0000-1000-8000-000000000003',
        'john-user' => '20000000-0000-1000-8000-000000000004',
        'john-admin' => '20000000-0000-1000-8000-000000000005',
        'john-root' => '20000000-0000-1000-8000-000000000006',
        'alice' => '20000000-0000-1000-8000-000000000007',
        'bruno' => '20000000-0000-1000-8000-000000000008',
        'clara' => '20000000-0000-1000-8000-000000000009',
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
        array_map(
            fn (?string $role): bool => $this->createRoleBasedUser($manager, $role),
            [
                null,
                ...$this->rolesService->getRoles(),
            ],
        );

        $this->createNamedUser($manager, 'alice', 'Alice', 'Martin', 'alice.martin@test.com', 'password-alice');
        $this->createNamedUser($manager, 'bruno', 'Bruno', 'Lopez', 'bruno.lopez@test.com', 'password-bruno');
        $this->createNamedUser($manager, 'clara', 'Clara', 'Nguyen', 'clara.nguyen@test.com', 'password-clara');

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 3;
    }

    public static function getUuidByKey(string $key): string
    {
        return self::$uuids[$key];
    }

    /**
     * @throws Throwable
     */
    private function createRoleBasedUser(ObjectManager $manager, ?string $role = null): true
    {
        $suffix = $role === null ? '' : '-' . $this->rolesService->getShort($role);

        $entity = new User()
            ->setUsername('john' . $suffix)
            ->setFirstName('John')
            ->setLastName('Doe')
            ->setEmail('john.doe' . $suffix . '@test.com')
            ->setLanguage(Language::EN)
            ->setLocale(Locale::EN)
            ->setPlainPassword('password' . $suffix);

        if ($role !== null) {
            /** @var UserGroup $userGroup */
            $userGroup = $this->getReference('UserGroup-' . $this->rolesService->getShort($role), UserGroup::class);
            $entity->addUserGroup($userGroup);
        }

        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids['john' . $suffix]), $entity);

        $manager->persist($entity);
        $this->addReference('User-' . $entity->getUsername(), $entity);

        return true;
    }

    /**
     * @throws Throwable
     */
    private function createNamedUser(ObjectManager $manager, string $key, string $firstName, string $lastName, string $email, string $password): void
    {
        $entity = (new User())
            ->setUsername($key)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setEmail($email)
            ->setLanguage(Language::EN)
            ->setLocale(Locale::EN)
            ->setPlainPassword($password);

        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids[$key]), $entity);

        $manager->persist($entity);
        $this->addReference('User-' . $entity->getUsername(), $entity);
    }
}
