<?php

declare(strict_types=1);

namespace App\Role\Domain\Enum;

/**
 * Enum Role
 *
 * @package App\Role
 */
enum Role: string
{
    case LOGGED = 'ROLE_LOGGED';
    case USER = 'ROLE_USER';
    case ADMIN = 'ROLE_ADMIN';
    case ROOT = 'ROLE_ROOT';
    case API = 'ROLE_API';
    case RECRUITER = 'ROLE_RECRUITER';
    case HIRING_MANAGER = 'ROLE_HIRING_MANAGER';
    case INTERVIEWER = 'ROLE_INTERVIEWER';
    case CRM_OWNER = 'ROLE_CRM_OWNER';
    case CRM_ADMIN = 'ROLE_CRM_ADMIN';
    case CRM_MANAGER = 'ROLE_CRM_MANAGER';
    case CRM_SALES = 'ROLE_CRM_SALES';
    case CRM_SUPPORT = 'ROLE_CRM_SUPPORT';
    case CRM_MARKETING = 'ROLE_CRM_MARKETING';
    case CRM_VIEWER = 'ROLE_CRM_VIEWER';

    public function label(): string
    {
        return match ($this) {
            self::LOGGED => 'Logged in users',
            self::USER => 'Normal users',
            self::ADMIN => 'Admin users',
            self::ROOT => 'Root users',
            self::API => 'API users',
            self::RECRUITER => 'Recruiters',
            self::HIRING_MANAGER => 'Hiring managers',
            self::INTERVIEWER => 'Interviewers',
            self::CRM_OWNER => 'CRM owners',
            self::CRM_ADMIN => 'CRM admins',
            self::CRM_MANAGER => 'CRM managers',
            self::CRM_SALES => 'CRM sales',
            self::CRM_SUPPORT => 'CRM support',
            self::CRM_MARKETING => 'CRM marketing',
            self::CRM_VIEWER => 'CRM viewers',
        };
    }
}
