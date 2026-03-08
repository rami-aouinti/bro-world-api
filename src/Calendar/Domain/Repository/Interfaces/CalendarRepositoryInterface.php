<?php

declare(strict_types=1);

namespace App\Calendar\Domain\Repository\Interfaces;

use App\Calendar\Domain\Entity\Calendar;
use App\Platform\Domain\Entity\Application;

interface CalendarRepositoryInterface
{
    public function findOneByApplication(Application $application): ?Calendar;
}
