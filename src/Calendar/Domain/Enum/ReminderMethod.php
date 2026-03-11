<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Enum;

enum ReminderMethod: string
{
    case EMAIL = 'email';
    case POPUP = 'popup';
    case SMS = 'sms';
}
