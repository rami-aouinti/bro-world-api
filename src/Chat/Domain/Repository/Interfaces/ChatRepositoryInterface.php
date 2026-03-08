<?php

declare(strict_types=1);

namespace App\Chat\Domain\Repository\Interfaces;

use App\Chat\Domain\Entity\Chat;
use App\Platform\Domain\Entity\Application;

interface ChatRepositoryInterface
{
    public function findOneByApplication(Application $application): ?Chat;
}
