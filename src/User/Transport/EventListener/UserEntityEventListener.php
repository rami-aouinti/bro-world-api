<?php

declare(strict_types=1);

namespace App\User\Transport\EventListener;

use App\Configuration\Domain\Entity\Configuration;
use App\Configuration\Domain\Enum\ConfigurationScope;
use App\User\Application\Security\SecurityUser;
use App\User\Domain\Entity\User;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use LengthException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use function strlen;

/**
 * @package App\User
 */
class UserEntityEventListener
{
    private const string USER_NOTIFICATION_PREFERENCES_KEY = 'user.notifications.preferences';

    /**
     * @var array<int, array{switchState: bool, text: string}>
     */
    private const array DEFAULT_USER_NOTIFICATION_PREFERENCES = [
        [
            'switchState' => true,
            'text' => 'Email me when someone follows me',
        ],
        [
            'switchState' => false,
            'text' => 'Email me when someone answers on...',
        ],
        [
            'switchState' => true,
            'text' => 'Email me when someone mentions me...',
        ],
        [
            'switchState' => false,
            'text' => 'New launches and projects',
        ],
        [
            'switchState' => true,
            'text' => 'Monthly product updates',
        ],
        [
            'switchState' => false,
            'text' => 'Subscribe to newsletter',
        ],
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $userPasswordHasher,
    ) {
    }

    /**
     * Doctrine lifecycle event for 'prePersist' event.
     */
    public function prePersist(LifecycleEventArgs $event): void
    {
        $this->process($event);

        $user = $event->getObject();

        if ($user instanceof User) {
            $this->createDefaultUserConfigurations($event, $user);
        }
    }

    /**
     * Doctrine lifecycle event for 'preUpdate' event.
     */
    public function preUpdate(LifecycleEventArgs $event): void
    {
        $this->process($event);
    }

    /**
     * @throws LengthException
     */
    private function process(LifecycleEventArgs $event): void
    {
        // Get user entity object
        $user = $event->getObject();

        // Valid user so lets change password
        if ($user instanceof User) {
            $user->ensureGeneratedPhoto();
            $this->changePassword($user);
        }
    }

    private function createDefaultUserConfigurations(LifecycleEventArgs $event, User $user): void
    {
        $configuration = new Configuration();
        $configuration
            ->setConfigurationKey(self::USER_NOTIFICATION_PREFERENCES_KEY)
            ->setUser($user)
            ->setConfigurationValue(self::DEFAULT_USER_NOTIFICATION_PREFERENCES)
            ->setScope(ConfigurationScope::USER)
            ->setPrivate(true);

        $event->getObjectManager()->persist($configuration);
    }

    /**
     * Method to change user password whenever it's needed.
     *
     * @throws LengthException
     */
    private function changePassword(User $user): void
    {
        // Get plain password from user entity
        $plainPassword = $user->getPlainPassword();

        // Yeah, we have new plain password set, so we need to encode it
        if ($plainPassword !== '') {
            if (strlen($plainPassword) < User::PASSWORD_MIN_LENGTH) {
                throw new LengthException(
                    'Too short password, should be at least ' . User::PASSWORD_MIN_LENGTH . ' symbols'
                );
            }

            // Password hash callback
            $callback = fn (string $plainPassword): string => $this->userPasswordHasher
                ->hashPassword(new SecurityUser($user, []), $plainPassword);
            // Set new password and encode it with user encoder
            $user->setPassword($callback, $plainPassword);
            // And clean up plain password from entity
            $user->eraseCredentials();
        }
    }
}
