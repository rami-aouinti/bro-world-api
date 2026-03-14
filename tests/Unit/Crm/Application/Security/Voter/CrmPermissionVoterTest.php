<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Application\Security\Voter;

use App\Crm\Application\Security\CrmPermissions;
use App\Crm\Application\Security\Voter\CrmPermissionVoter;
use App\Role\Domain\Entity\Role as RoleEntity;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserGroup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class CrmPermissionVoterTest extends TestCase
{
    public function testSupportsCrmPermissionsOnly(): void
    {
        $voter = new TestableCrmPermissionVoter();

        self::assertTrue($voter->supportsAttribute(CrmPermissions::VIEW));
        self::assertTrue($voter->supportsAttribute(CrmPermissions::EDIT));
        self::assertTrue($voter->supportsAttribute(CrmPermissions::MANAGE));
        self::assertFalse($voter->supportsAttribute('UNKNOWN_PERMISSION'));
    }

    public function testViewPermissionGrantedWithCrmViewerGroupRole(): void
    {
        $voter = new TestableCrmPermissionVoter();
        $user = $this->createUserWithRoles([Role::CRM_VIEWER]);

        self::assertTrue($voter->voteAttribute(CrmPermissions::VIEW, $this->createToken($user)));
        self::assertFalse($voter->voteAttribute(CrmPermissions::EDIT, $this->createToken($user)));
    }

    public function testRootRoleAlwaysGranted(): void
    {
        $voter = new TestableCrmPermissionVoter();
        $user = $this->createUserWithRoles([Role::ROOT]);

        self::assertTrue($voter->voteAttribute(CrmPermissions::VIEW, $this->createToken($user)));
        self::assertTrue($voter->voteAttribute(CrmPermissions::EDIT, $this->createToken($user)));
        self::assertTrue($voter->voteAttribute(CrmPermissions::MANAGE, $this->createToken($user)));
    }

    /**
     * @param list<Role> $roles
     */
    private function createUserWithRoles(array $roles): User
    {
        $user = new User();

        foreach ($roles as $role) {
            $group = (new UserGroup())
                ->setName($role->value)
                ->setRole(new RoleEntity($role->value));

            $user->addUserGroup($group);
        }

        return $user;
    }

    private function createToken(User $user): TokenInterface&MockObject
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}

final class TestableCrmPermissionVoter extends CrmPermissionVoter
{
    public function supportsAttribute(string $attribute): bool
    {
        return $this->supports($attribute, null);
    }

    public function voteAttribute(string $attribute, TokenInterface $token): bool
    {
        return $this->voteOnAttribute($attribute, null, $token);
    }
}
