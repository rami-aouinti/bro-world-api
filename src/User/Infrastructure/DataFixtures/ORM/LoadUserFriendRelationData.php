<?php

declare(strict_types=1);

namespace App\User\Infrastructure\DataFixtures\ORM;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserFriendRelation;
use App\User\Domain\Enum\FriendStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

final class LoadUserFriendRelationData extends Fixture implements DependentFixtureInterface
{
    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        /** @var User $johnRoot */
        $johnRoot = $this->getReference('User-john-root', User::class);
        /** @var User $alice */
        $alice = $this->getReference('User-alice', User::class);
        /** @var User $bruno */
        $bruno = $this->getReference('User-bruno', User::class);
        /** @var User $clara */
        $clara = $this->getReference('User-clara', User::class);
        /** @var User $bob */
        $bob = $this->getReference('User-bob', User::class);
        /** @var User $charlie */
        $charlie = $this->getReference('User-charlie', User::class);
        /** @var User $diana */
        $diana = $this->getReference('User-diana', User::class);
        /** @var User $emma */
        $emma = $this->getReference('User-emma', User::class);
        /** @var User $felix */
        $felix = $this->getReference('User-felix', User::class);
        /** @var User $grace */
        $grace = $this->getReference('User-grace', User::class);

        $relations = [
            (new UserFriendRelation())
                ->setRequester($alice)
                ->setAddressee($johnRoot)
                ->setStatus(FriendStatus::PENDING),
            (new UserFriendRelation())
                ->setRequester($johnRoot)
                ->setAddressee($bruno)
                ->setStatus(FriendStatus::PENDING),
            (new UserFriendRelation())
                ->setRequester($johnRoot)
                ->setAddressee($clara)
                ->setStatus(FriendStatus::REJECTED),
            (new UserFriendRelation())
                ->setRequester($diana)
                ->setAddressee($johnRoot)
                ->setStatus(FriendStatus::REJECTED),
            (new UserFriendRelation())
                ->setRequester($johnRoot)
                ->setAddressee($emma)
                ->setStatus(FriendStatus::ACCEPTED),
            (new UserFriendRelation())
                ->setRequester($felix)
                ->setAddressee($johnRoot)
                ->setStatus(FriendStatus::ACCEPTED),
            (new UserFriendRelation())
                ->setRequester($johnRoot)
                ->setAddressee($grace)
                ->setStatus(FriendStatus::ACCEPTED),
            (new UserFriendRelation())
                ->setRequester($charlie)
                ->setAddressee($johnRoot)
                ->setStatus(FriendStatus::BLOCKED),
            (new UserFriendRelation())
                ->setRequester($bob)
                ->setAddressee($alice)
                ->setStatus(FriendStatus::PENDING),
        ];

        foreach ($relations as $relation) {
            $manager->persist($relation);
        }

        $manager->flush();
    }

    /**
     * @return array<int, class-string>
     */
    #[Override]
    public function getDependencies(): array
    {
        return [LoadUserData::class];
    }
}
