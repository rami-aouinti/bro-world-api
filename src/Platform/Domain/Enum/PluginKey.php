<?php

declare(strict_types=1);

namespace App\Platform\Domain\Enum;

/**
 * @package App\Platform
 */
enum PluginKey: string
{
    case CALENDAR = 'calendar';
    case CHAT = 'chat';
    case BLOG = 'blog';
    case QUIZ = 'quiz';
    case LANGUAGE = 'language';
}
