<?php

declare(strict_types=1);

namespace App\Abonnement\Infrastructure\DataFixtures\ORM;

use App\Abonnement\Domain\Entity\News;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadNewsData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $news1 = (new News())
            ->setTitle('Maintenance plateforme')
            ->setDescription('<p>Une maintenance est prévue ce soir à 23h00 UTC.</p>')
            ->setExecuteAt(new DateTimeImmutable('-1 day'))
            ->setExecuted(false);

        $news2 = (new News())
            ->setTitle('Nouveautés du mois')
            ->setDescription('<h2>Release notes</h2><p>Nouvelles fonctionnalités disponibles.</p>')
            ->setExecuteAt(new DateTimeImmutable('+1 day'))
            ->setExecuted(false);

        $manager->persist($news1);
        $manager->persist($news2);
        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 100;
    }
}
