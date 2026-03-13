<?php

declare(strict_types=1);

namespace App\User\Infrastructure\DataFixtures\ORM;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserStory;
use DateInterval;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadUserStoryData extends Fixture implements DependentFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $emma */
        $emma = $this->getReference('User-emma', User::class);
        /** @var User $felix */
        $felix = $this->getReference('User-felix', User::class);
        /** @var User $alice */
        $alice = $this->getReference('User-alice', User::class);

        $now = new DateTimeImmutable();

        $activeMine = (new UserStory())
            ->setUser($johnRoot)
            ->setImageUrl('https://cdn.example.com/stories/john-1.jpg')
            ->setCreatedAt($now->sub(new DateInterval('PT2H')))
            ->setExpiresAt($now->add(new DateInterval('PT22H')));

        $activeMineSecond = (new UserStory())
            ->setUser($johnRoot)
            ->setImageUrl('https://cdn.example.com/stories/john-2.jpg')
            ->setCreatedAt($now->sub(new DateInterval('PT5H')))
            ->setExpiresAt($now->add(new DateInterval('PT19H')));

        $activeEmma = (new UserStory())
            ->setUser($emma)
            ->setImageUrl('https://cdn.example.com/stories/emma-1.jpg')
            ->setCreatedAt($now->sub(new DateInterval('PT3H')))
            ->setExpiresAt($now->add(new DateInterval('PT21H')));

        $activeFelix = (new UserStory())
            ->setUser($felix)
            ->setImageUrl('https://cdn.example.com/stories/felix-1.jpg')
            ->setCreatedAt($now->sub(new DateInterval('PT8H')))
            ->setExpiresAt($now->add(new DateInterval('PT16H')));

        $expiredAlice = (new UserStory())
            ->setUser($alice)
            ->setImageUrl('https://cdn.example.com/stories/alice-expired.jpg')
            ->setCreatedAt($now->sub(new DateInterval('PT30H')))
            ->setExpiresAt($now->sub(new DateInterval('PT6H')));

        foreach ([$activeMine, $activeMineSecond, $activeEmma, $activeFelix, $expiredAlice] as $story) {
            $manager->persist($story);
        }

        $manager->flush();
    }

    /**
     * @return array<int, class-string>
     */
    #[Override]
    public function getDependencies(): array
    {
        return [
            LoadUserData::class,
            LoadUserFriendRelationData::class,
        ];
    }
}
