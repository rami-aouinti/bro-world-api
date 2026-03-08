<?php

declare(strict_types=1);

namespace App\Crm\Infrastructure\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadCrmData extends Fixture
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $manager->flush();
    }
}
