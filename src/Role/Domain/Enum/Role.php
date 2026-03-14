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
        };
    }
}
