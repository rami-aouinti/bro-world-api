<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\Platform\Application\Service\PluginProvisioning\BlogPluginProvisioner;
use App\Platform\Application\Service\PluginProvisioning\CalendarPluginProvisioner;
use App\Platform\Application\Service\PluginProvisioning\ChatPluginProvisioner;
use App\Platform\Application\Service\PluginProvisioning\QuizPluginProvisioner;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PluginKey;

use function in_array;

final class ApplicationPluginProvisioningService
{
    public function __construct(
        private readonly CalendarPluginProvisioner $calendarPluginProvisioner,
        private readonly ChatPluginProvisioner $chatPluginProvisioner,
        private readonly BlogPluginProvisioner $blogPluginProvisioner,
        private readonly QuizPluginProvisioner $quizPluginProvisioner,
    ) {
    }

    /**
     * @param array<int, PluginKey> $pluginKeys
     */
    public function provision(Application $application, array $pluginKeys): void
    {
        if (in_array(PluginKey::CALENDAR, $pluginKeys, true)) {
            $this->calendarPluginProvisioner->provision($application);
        }

        if (in_array(PluginKey::CHAT, $pluginKeys, true)) {
            $this->chatPluginProvisioner->provision($application);
        }
        if (in_array(PluginKey::BLOG, $pluginKeys, true)) {
            $this->blogPluginProvisioner->provision($application);
        }

        if (in_array(PluginKey::QUIZ, $pluginKeys, true)) {
            $this->quizPluginProvisioner->provision($application);
        }
    }
}
