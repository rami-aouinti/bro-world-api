<?php

declare(strict_types=1);

namespace App\Crm\Application\Security\Voter;

use App\Crm\Application\Security\CrmPermissions;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserGroup;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

final class CrmPermissionVoter extends Voter
{
    /**
     * @var array<string, array<int, Role>>
     */
    private const array PERMISSION_MATRIX = [
        CrmPermissions::VIEW => [
            Role::CRM_OWNER,
            Role::CRM_ADMIN,
            Role::CRM_MANAGER,
            Role::CRM_SALES,
            Role::CRM_SUPPORT,
            Role::CRM_MARKETING,
            Role::CRM_VIEWER,
        ],
        CrmPermissions::EDIT => [
            Role::CRM_OWNER,
            Role::CRM_ADMIN,
            Role::CRM_MANAGER,
            Role::CRM_SALES,
            Role::CRM_SUPPORT,
            Role::CRM_MARKETING,
        ],
        CrmPermissions::MANAGE => [
            Role::CRM_OWNER,
            Role::CRM_ADMIN,
            Role::CRM_MANAGER,
        ],
    ];

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::PERMISSION_MATRIX[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getUserGroups()->map(
            static fn (UserGroup $userGroup): string => $userGroup->getRole()->getId(),
        )->toArray();

        if (in_array(Role::ROOT->value, $roles, true)) {
            return true;
        }

        return $this->hasAnyRole($roles, self::PERMISSION_MATRIX[$attribute] ?? []);
    }

    /**
     * @param array<int, string> $userRoles
     * @param array<int, Role> $roles
     */
    private function hasAnyRole(array $userRoles, array $roles): bool
    {
        foreach ($roles as $role) {
            if (in_array($role->value, $userRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
