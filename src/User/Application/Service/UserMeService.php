<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\General\Application\Message\EntityDeleted;
use App\General\Application\Message\EntityPatched;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\Log\Domain\Entity\LogLogin;
use App\Log\Infrastructure\Repository\LogLoginRepository;
use App\Platform\Domain\Entity\Application;
use App\User\Application\Security\SecurityUser;
use App\User\Domain\Entity\Social;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserProfile;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;

use function array_key_exists;
use function array_map;
use function in_array;
use function is_array;
use function sprintf;
use function trim;
use function uniqid;

readonly class UserMeService
{
    private const string USER_ENTITY_TYPE = 'user_user';
    private const int CACHE_TTL_SECONDS = 120;

    public function __construct(
        private LogLoginRepository $logLoginRepository,
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private ElasticsearchServiceInterface $elasticsearchService,
        private MessageBusInterface $messageBus,
        private UserPasswordHasherInterface $passwordHasher,
        private UserFriendService $userFriendService
    ) {
    }

    /**
     * @return array<int,array<string,string>>
     * @throws InvalidArgumentException
     */
    public function getSessions(User $user): array
    {
        $cacheKey = sprintf('user_sessions_%s', $user->getId());

        /** @var array<int,array<string,string>> $sessions */
        $sessions = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $qb = $this->logLoginRepository->createQueryBuilder('log')
                ->andWhere('log.user = :user')
                ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
                ->orderBy('log.time', 'DESC')
                ->setMaxResults(10);

            return array_map(static function (LogLogin $log): array {
                $device = $log->getDeviceName() ?? 'desktop';
                $icon = $device === 'smartphone' ? 'mdi-cellphone' : 'mdi-desktop-classic';

                return [
                    'icon' => $icon,
                    'title' => trim(($log->getClientName() ?? 'Unknown') . ' on ' . ($log->getOsName() ?? 'Unknown')),
                    'description' => '',
                    'badge' => 'Active',
                    'city' => 'Unknown',
                    'ip' => $log->getClientIp(),
                ];
            }, $qb->getQuery()->getResult());
        });

        if ($sessions === []) {
            return [[
                'icon' => 'mdi-account-circle',
                'title' => 'Current session',
                'description' => '',
                'badge' => 'Active',
                'city' => 'Unknown',
                'ip' => 'Unknown',
            ]];
        }

        return $sessions;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function getApplications(User $user, int $limit = 0): array
    {
        $cacheKey = sprintf('user_applications_%s_%d', $user->getId(), $limit);

        /** @var array<int,array<string,mixed>> $applications */
        $applications = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user, $limit): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            $qb = $this->entityManager->getRepository(Application::class)
                ->createQueryBuilder('application')
                ->leftJoin('application.platform', 'platform')
                ->addSelect('platform')
                ->andWhere('application.user = :user')
                ->setParameter('user', $user->getId(), UuidBinaryOrderedTimeType::NAME)
                ->orderBy('application.createdAt', 'DESC');

            if ($limit > 0) {
                $qb->setMaxResults($limit);
            }

            /** @var array<int, Application> $entities */
            $entities = $qb->getQuery()->getResult();

            return array_map(static fn (Application $application): array => [
                'id' => $application->getId(),
                'platformId' => $application->getPlatform()?->getId(),
                'platformName' => $application->getPlatform()?->getName(),
                'title' => $application->getTitle(),
                'slug' => $application->getSlug(),
                'description' => $application->getDescription(),
                'status' => $application->getStatus()->value,
                'private' => $application->isPrivate(),
                'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                'updatedAt' => $application->getUpdatedAt()?->format(DATE_ATOM),
            ], $entities);
        });

        return $applications;
    }

    /**
     * @return array<string,mixed>
     * @throws InvalidArgumentException
     */
    public function getMe(User $user): array
    {
        $cacheKey = sprintf('user_me_%s', $user->getId());

        /** @var array<string,mixed> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $item->expiresAfter(self::CACHE_TTL_SECONDS);

            return $this->buildMePayload($user);
        });

        return $payload;
    }

    /**
     * @param array<string,mixed> $payload
     * @throws \DateMalformedStringException
     * @throws ExceptionInterface
     */
    public function patchProfile(User $user, array $payload): array
    {
        $profile = $this->ensureProfile($user);

        if (array_key_exists('title', $payload)) {
            $profile->setTitle($this->nullableString($payload['title']));
        }
        if (array_key_exists('information', $payload)) {
            $profile->setInformation($this->nullableString($payload['information']));
        }
        if (array_key_exists('gender', $payload)) {
            $gender = $this->nullableString($payload['gender']);
            if ($gender !== null && in_array($gender, ['Female', 'Male'], true) === false) {
                throw new BadRequestHttpException('gender must be Female or Male');
            }
            $profile->setGender($gender);
        }
        if (array_key_exists('birthday', $payload)) {
            $profile->setBirthday($payload['birthday'] !== null && $payload['birthday'] !== '' ? new DateTimeImmutable((string)$payload['birthday']) : null);
        }
        if (array_key_exists('location', $payload)) {
            $profile->setLocation($this->nullableString($payload['location']));
        }
        if (array_key_exists('phone', $payload)) {
            $profile->setPhone($this->nullableString($payload['phone']));
        }
        if (array_key_exists('firstName', $payload)) {
            $user->setFirstName($this->nullableString($payload['firstName']));
        }
        if (array_key_exists('lastName', $payload)) {
            $user->setLastName($this->nullableString($payload['lastName']));
        }
        if (array_key_exists('email', $payload)) {
            $user->setEmail($this->nullableString($payload['email']));
        }

        if (array_key_exists('socials', $payload) && is_array($payload['socials'])) {
            foreach ($user->getSocials()->toArray() as $social) {
                $user->removeSocial($social);
                $this->entityManager->remove($social);
            }

            foreach ($payload['socials'] as $socialData) {
                if (!is_array($socialData) || !isset($socialData['provider'], $socialData['providerId'])) {
                    continue;
                }

                $social = new Social();
                $social
                    ->setProvider((string)$socialData['provider'])
                    ->setProviderId((string)$socialData['providerId']);

                $user->addSocial($social);
                $this->entityManager->persist($social);
            }
        }

        $this->entityManager->persist($profile);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new EntityPatched(uniqid('op_', true), self::USER_ENTITY_TYPE, $user->getId()));
        $this->indexUser($user, $profile);
        $this->invalidateMeCaches($user->getId());

        return $this->normalizeProfile($profile);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function changePassword(User $user, array $payload): void
    {
        $currentPassword = (string)($payload['currentPassword'] ?? '');
        $newPassword = (string)($payload['newPassword'] ?? '');

        if ($this->passwordHasher->isPasswordValid(new SecurityUser($user, []), $currentPassword) === false) {
            throw new BadRequestHttpException('Current password is invalid');
        }

        $user->setPlainPassword($newPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->indexUser($user, $this->ensureProfile($user));
        $this->invalidateMeCaches($user->getId());
    }

    public function deleteMe(User $user): void
    {
        $userId = $user->getId();
        $this->invalidateMeCaches($userId);
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new EntityDeleted(uniqid('op_', true), self::USER_ENTITY_TYPE, $userId));

        try {
            $this->elasticsearchService->delete('users', $userId);
        } catch (Throwable) {
        }
    }

    /**
     * @return array<string,mixed>
     * @throws InvalidArgumentException
     */
    private function buildMePayload(User $user): array
    {
        $profile = $this->ensureProfile($user);

        return [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'photo' => $user->getPhoto(),
            'profile' => $this->normalizeProfile($profile),
            'socials' => array_map(static fn (Social $social): array => [
                'provider' => $social->getProvider(),
                'providerId' => $social->getProviderId(),
            ], $user->getSocials()->toArray()),
            'sessions' => $this->getSessions($user),
            'applications' => $this->getApplications($user),
            'friends' => $this->userFriendService->getMyFriends($user),
            'friendRequests' => $this->userFriendService->getMySentRequests($user),
            'blockedUsers' => $this->userFriendService->getMyBlockedUsers($user),
            'incomingRequests' => $this->userFriendService->getMyIncomingRequests($user),
        ];
    }

    private function ensureProfile(User $user): UserProfile
    {
        $profile = $user->getProfile();

        if ($profile === null) {
            $profile = new UserProfile();
            $profile->setUser($user);
            $user->setProfile($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
        }

        return $profile;
    }

    /**
     * @return array<string,mixed>
     */
    private function normalizeProfile(UserProfile $profile): array
    {
        return [
            'title' => $profile->getTitle(),
            'information' => $profile->getInformation(),
            'gender' => $profile->getGender(),
            'birthday' => $profile->getBirthday()?->format('Y-m-d'),
            'location' => $profile->getLocation(),
            'phone' => $profile->getPhone(),
        ];
    }

    private function indexUser(User $user, UserProfile $profile): void
    {
        try {
            $this->elasticsearchService->index('users', $user->getId(), [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'firstName' => $user->getFirstName(),
                'lastName' => $user->getLastName(),
                'title' => $profile->getTitle(),
                'information' => $profile->getInformation(),
                'location' => $profile->getLocation(),
                'updatedAt' => $user->getUpdatedAt()?->format(DATE_ATOM),
            ]);
        } catch (Throwable) {
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string)$value);

        return $normalized === '' ? null : $normalized;
    }

    private function invalidateMeCaches(string $userId): void
    {
        $this->cache->delete(sprintf('user_me_%s', $userId));
        $this->cache->delete(sprintf('user_sessions_%s', $userId));
        $this->cache->delete(sprintf('user_applications_%s_0', $userId));
        $this->cache->delete(sprintf('user_applications_%s_3', $userId));
    }
}
