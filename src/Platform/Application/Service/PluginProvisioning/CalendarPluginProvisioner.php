<?php

declare(strict_types=1);

namespace App\Platform\Application\Service\PluginProvisioning;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Infrastructure\Repository\CalendarRepository;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\Platform\Domain\Entity\Application;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class CalendarPluginProvisioner
{
    public function __construct(
        private CalendarRepository $calendarRepository,
        private EventRepository $eventRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Application $application): void
    {
        $calendar = $this->calendarRepository->findOneByApplication($application);
        if (!$calendar instanceof Calendar) {
            $calendar = (new Calendar())
                ->setTitle('Default calendar')
                ->setUser($application->getUser())
                ->setApplication($application);

            $this->entityManager->persist($calendar);
        }

        $event = $this->eventRepository->findOneBy([
            'calendar' => $calendar,
            'title' => 'Welcome event',
        ]);

        if ($event instanceof Event) {
            return;
        }

        $startAt = new DateTimeImmutable('+1 day 09:00');

        $event = (new Event())
            ->setCalendar($calendar)
            ->setUser($application->getUser())
            ->setTitle('Welcome event')
            ->setDescription('Initial planning event for your team.')
            ->setStartAt($startAt)
            ->setEndAt($startAt->modify('+1 hour'));

        $this->entityManager->persist($event);
    }
}
