<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\DataFixtures\ORM;

use App\General\Domain\Rest\UuidHelper;
use App\Notification\Domain\Entity\Notification;
use App\Tests\Utils\PhpUnitUtil;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

final class LoadNotificationData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<int, array{uuid: non-empty-string, title: non-empty-string, description: non-empty-string, type: non-empty-string, recipientRef: non-empty-string, fromRef: non-empty-string|null, read: bool}>
     */
    private const array DATA = [
        [
            'uuid' => '70000000-0000-1000-8000-000000000001',
            'title' => 'System maintenance',
            'description' => 'A maintenance window is planned for tonight.',
            'type' => 'system',
            'recipientRef' => 'User-john-user',
            'fromRef' => null,
            'read' => false,
        ],
        [
            'uuid' => '70000000-0000-1000-8000-000000000002',
            'title' => 'Profile warning',
            'description' => 'Your profile is missing a required document.',
            'type' => 'warning',
            'recipientRef' => 'User-john-admin',
            'fromRef' => 'User-john-root',
            'read' => true,
        ],
        [
            'uuid' => '70000000-0000-1000-8000-000000000003',
            'title' => 'Welcome',
            'description' => 'Welcome to the notification module.',
            'type' => 'info',
            'recipientRef' => 'User-john-root',
            'fromRef' => 'User-john-admin',
            'read' => false,
        ],

        [
            'uuid' => '70000000-0000-1000-8000-000000000004',
            'title' => 'Team update',
            'description' => 'A new update is available for your team.',
            'type' => 'info',
            'recipientRef' => 'User-john-root',
            'fromRef' => 'User-john-user',
            'read' => true,
        ],
        [
            'uuid' => '70000000-0000-1000-8000-000000000005',
            'title' => 'Security alert',
            'description' => 'A login from a new device was detected.',
            'type' => 'security',
            'recipientRef' => 'User-john-root',
            'fromRef' => null,
            'read' => false,
        ],

    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach (self::DATA as $item) {
            /** @var User $recipient */
            $recipient = $this->getReference($item['recipientRef'], User::class);
            /** @var User|null $from */
            $from = $item['fromRef'] !== null ? $this->getReference($item['fromRef'], User::class) : null;

            $notification = (new Notification())
                ->setTitle($item['title'])
                ->setDescription($item['description'])
                ->setType($item['type'])
                ->setRecipient($recipient)
                ->setFrom($from)
                ->setRead($item['read']);

            PhpUnitUtil::setProperty('id', UuidHelper::fromString($item['uuid']), $notification);

            $manager->persist($notification);
            $this->addReference('Notification-' . $item['uuid'], $notification);
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 4;
    }
}
