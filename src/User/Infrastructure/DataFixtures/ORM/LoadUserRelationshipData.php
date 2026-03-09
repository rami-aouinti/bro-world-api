<?php

declare(strict_types=1);

namespace App\User\Infrastructure\DataFixtures\ORM;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserRelationship;
use App\User\Domain\Enum\UserRelationshipStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadUserRelationshipData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $alice = $this->getReference('User-alice', User::class);
        $bob = $this->getReference('User-bob', User::class);
        $charlie = $this->getReference('User-charlie', User::class);
        $diana = $this->getReference('User-diana', User::class);

        $pendingRelationship = (new UserRelationship())
            ->setRequester($alice)
            ->setAddressee($bob)
            ->setStatus(UserRelationshipStatus::PENDING);

        $acceptedRelationship = (new UserRelationship())
            ->setRequester($bob)
            ->setAddressee($charlie)
            ->setStatus(UserRelationshipStatus::ACCEPTED);

        $blockedRelationship = (new UserRelationship())
            ->setRequester($charlie)
            ->setAddressee($diana)
            ->setStatus(UserRelationshipStatus::BLOCKED)
            ->setBlockedBy($diana);

        $manager->persist($pendingRelationship);
        $manager->persist($acceptedRelationship);
        $manager->persist($blockedRelationship);

        $this->addReference('UserRelationship-pending', $pendingRelationship);
        $this->addReference('UserRelationship-accepted', $acceptedRelationship);
        $this->addReference('UserRelationship-blocked', $blockedRelationship);

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 5;
    }
}
