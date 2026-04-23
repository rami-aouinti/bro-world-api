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
    case SHOP_VIEWER = 'ROLE_SHOP_VIEWER';
    case SHOP_EDITOR = 'ROLE_SHOP_EDITOR';
    case SHOP_MANAGER = 'ROLE_SHOP_MANAGER';
    case SCHOOL_VIEWER = 'ROLE_SCHOOL_VIEWER';
    case SCHOOL_EDITOR = 'ROLE_SCHOOL_EDITOR';
    case SCHOOL_MANAGER = 'ROLE_SCHOOL_MANAGER';
    case JOB_VIEWER = 'ROLE_JOB_VIEWER';
    case JOB_EDITOR = 'ROLE_JOB_EDITOR';
    case JOB_MANAGER = 'ROLE_JOB_MANAGER';

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
            self::SHOP_VIEWER => 'Shop viewers',
            self::SHOP_EDITOR => 'Shop editors',
            self::SHOP_MANAGER => 'Shop managers',
            self::SCHOOL_VIEWER => 'School viewers',
            self::SCHOOL_EDITOR => 'School editors',
            self::SCHOOL_MANAGER => 'School managers',
            self::JOB_VIEWER => 'Job viewers',
            self::JOB_EDITOR => 'Job editors',
            self::JOB_MANAGER => 'Job managers',
        };
    }
}
