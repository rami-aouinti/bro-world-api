<?php

declare(strict_types=1);

namespace App\Platform\Application\Service;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Chat\Domain\Entity\Chat;
use App\Chat\Infrastructure\Repository\ChatRepository;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PluginKey;
use Doctrine\ORM\EntityManagerInterface;

use function in_array;

final class ApplicationPluginProvisioningService
{
    public function __construct(
        private readonly CalendarRepository $calendarRepository,
        private readonly ChatRepository $chatRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param array<int, PluginKey> $pluginKeys
     */
    public function provision(Application $application, array $pluginKeys): void
    {
        if (in_array(PluginKey::CALENDAR, $pluginKeys, true)) {
            $this->provisionCalendar($application);
        }

        if (in_array(PluginKey::CHAT, $pluginKeys, true)) {
            $this->provisionChat($application);
        }
    }

    private function provisionCalendar(Application $application): void
    {
        if ($this->calendarRepository->findOneByApplication($application) instanceof Calendar) {
            return;
        }

        $calendar = (new Calendar())
            ->setTitle('Default calendar')
            ->setUser($application->getUser())
            ->setApplication($application);

        $this->entityManager->persist($calendar);
    }

    private function provisionChat(Application $application): void
    {
        if ($this->chatRepository->findOneByApplication($application) instanceof Chat) {
            return;
        }

        $application->ensureGeneratedSlug();

        $chat = (new Chat())
            ->setApplication($application)
            ->setApplicationSlug($application->getSlug());

        $this->entityManager->persist($chat);
    }
}
