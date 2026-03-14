<?php

declare(strict_types=1);

namespace App\Recruit\Application\Security;

final class RecruitPermissions
{
    public const string INTERVIEW_MANAGE = 'RECRUIT_INTERVIEW_MANAGE';
    public const string INTERVIEW_VIEW = 'RECRUIT_INTERVIEW_VIEW';
    public const string APPLICATION_STATUS_TRANSITION = 'RECRUIT_APPLICATION_STATUS_TRANSITION';
    public const string APPLICATION_STATUS_HISTORY_VIEW = 'RECRUIT_APPLICATION_STATUS_HISTORY_VIEW';
    public const string OFFER_MANAGE = 'RECRUIT_OFFER_MANAGE';
    public const string SENSITIVE_DATA_VIEW = 'RECRUIT_SENSITIVE_DATA_VIEW';
}
