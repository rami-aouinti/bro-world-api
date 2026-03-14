<?php

declare(strict_types=1);

namespace App\Recruit\Application\Security\Voter;

use App\Recruit\Application\Security\RecruitPermissions;
use App\User\Domain\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

use function in_array;

class RecruitPermissionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [
            RecruitPermissions::INTERVIEW_MANAGE,
            RecruitPermissions::INTERVIEW_VIEW,
            RecruitPermissions::APPLICATION_STATUS_TRANSITION,
            RecruitPermissions::APPLICATION_STATUS_HISTORY_VIEW,
            RecruitPermissions::OFFER_MANAGE,
            RecruitPermissions::SENSITIVE_DATA_VIEW,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ROOT', $roles, true) || in_array('ROLE_ADMIN', $roles, true)) {
            return true;
        }

        return match ($attribute) {
            RecruitPermissions::INTERVIEW_MANAGE => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true),
            RecruitPermissions::INTERVIEW_VIEW => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true) || in_array('ROLE_INTERVIEWER', $roles, true),
            RecruitPermissions::APPLICATION_STATUS_TRANSITION => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true),
            RecruitPermissions::APPLICATION_STATUS_HISTORY_VIEW => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true) || in_array('ROLE_INTERVIEWER', $roles, true),
            RecruitPermissions::OFFER_MANAGE => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true),
            RecruitPermissions::SENSITIVE_DATA_VIEW => in_array('ROLE_RECRUITER', $roles, true) || in_array('ROLE_HIRING_MANAGER', $roles, true),
            default => false,
        };
    }
}
