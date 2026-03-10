<?php

declare(strict_types=1);

namespace App\User\Infrastructure\DataFixtures\ORM;

use App\User\Domain\Entity\Social;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserProfile;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadUserProfileData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $johnRoot = $this->getReference('User-john-root', User::class);

        $profile = (new UserProfile())
            ->setUser($johnRoot)
            ->setTitle('CEO / Co-Founder')
            ->setInformation('Hi, I’m John Doe. If two equally difficult paths, choose the one more painful in the short term.')
            ->setGender('Male')
            ->setBirthday(new DateTimeImmutable('1987-05-18'))
            ->setLocation('Bucharest, EU')
            ->setPhone('+40 733 123 456');

        $johnRoot->setProfile($profile);

        $socials = [
            [
                'provider' => 'facebook',
                'providerId' => 'john.root.fb',
            ],
            [
                'provider' => 'instagram',
                'providerId' => 'john.root.ig',
            ],
            [
                'provider' => 'github',
                'providerId' => 'john-root',
            ],
        ];

        foreach ($socials as $item) {
            $social = (new Social())
                ->setUser($johnRoot)
                ->setProvider($item['provider'])
                ->setProviderId($item['providerId']);

            $johnRoot->addSocial($social);
            $manager->persist($social);
        }

        $manager->persist($profile);
        $manager->persist($johnRoot);

        $this->addReference('UserProfile-john-root', $profile);

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 4;
    }
}
