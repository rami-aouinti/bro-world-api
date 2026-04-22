<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\General\Domain\Service\Interfaces\MailerServiceInterface;
use App\Notification\Application\Service\NotificationPublisher;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserFriendRelation;
use App\User\Domain\Enum\FriendStatus;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Throwable;
use Twig\Environment as Twig;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

use function trim;

readonly class UserFriendService
{
    private const string FRIEND_NOTIFICATION_TYPE = 'friend_notification';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CacheInterface $cache,
        private NotificationPublisher $notificationPublisher,
        private MailerServiceInterface $mailerService,
        private Twig $twig,
        #[Autowire('%env(resolve:APP_SENDER_EMAIL)%')]
        private string $appSenderEmail,
    ) {
    }

    /**
     * @return array<string,string>
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws Throwable
     */
    public function sendRequest(User $loggedInUser, User $targetUser): array
    {
        $this->guardNotSelf($loggedInUser, $targetUser);

        $relation = $this->findRelation($loggedInUser, $targetUser);
        if ($relation !== null) {
            if ($relation->getStatus() === FriendStatus::BLOCKED) {
                throw new ConflictHttpException('Relation is blocked.');
            }

            if ($relation->getStatus() === FriendStatus::ACCEPTED) {
                throw new ConflictHttpException('Users are already friends.');
            }

            if ($relation->getStatus() === FriendStatus::PENDING) {
                throw new ConflictHttpException('Friend request already exists.');
            }
        }

        $relation = (new UserFriendRelation())
            ->setRequester($loggedInUser)
            ->setAddressee($targetUser)
            ->setStatus(FriendStatus::PENDING);

        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($targetUser);

        $this->notificationPublisher->publish(
            from: $loggedInUser,
            recipient: $targetUser,
            title: trim($loggedInUser->getFirstName() . ' ' . $loggedInUser->getLastName()) . ' sent you a friend request',
            type: self::FRIEND_NOTIFICATION_TYPE,
            description: $this->buildUserProfileLink($loggedInUser),
            metadata: [
                'event' => 'friend_request_sent',
                'friendStatus' => FriendStatus::PENDING->value,
                'requesterId' => $loggedInUser->getId(),
                'addresseeId' => $targetUser->getId(),
            ],
        );

        if ($targetUser->getEmail() !== '') {
            $body = $this->twig->render('Emails/friend_request_received.html.twig', [
                'requester' => $loggedInUser,
                'recipient' => $targetUser,
            ]);

            $this->mailerService->sendMail(
                'You received a friend request',
                $this->appSenderEmail,
                $targetUser->getEmail(),
                $body,
            );
        }

        return [
            'status' => 'request_sent',
        ];
    }

    /**
     * @return array<string,string>
     * @throws Throwable
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function acceptRequest(User $loggedInUser, User $requester): array
    {
        $relation = $this->findDirectRelation($requester, $loggedInUser);
        if ($relation === null || $relation->getStatus() !== FriendStatus::PENDING) {
            throw new NotFoundHttpException('Pending friend request not found.');
        }

        $relation->setStatus(FriendStatus::ACCEPTED);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($requester);

        $this->notificationPublisher->publish(
            from: $loggedInUser,
            recipient: $requester,
            title: trim($loggedInUser->getFirstName() . ' ' . $loggedInUser->getLastName()) . ' accepted your friend request',
            type: self::FRIEND_NOTIFICATION_TYPE,
            description: $this->buildUserProfileLink($loggedInUser),
            metadata: [
                'event' => 'friend_request_accepted',
                'friendStatus' => FriendStatus::ACCEPTED->value,
                'requesterId' => $requester->getId(),
                'addresseeId' => $loggedInUser->getId(),
            ],
        );

        if ($requester->getEmail() !== '') {
            $body = $this->twig->render('Emails/friend_request_accepted.html.twig', [
                'accepter' => $loggedInUser,
                'recipient' => $requester,
            ]);

            $this->mailerService->sendMail(
                'Your friend request has been accepted',
                $this->appSenderEmail,
                $requester->getEmail(),
                $body,
            );
        }

        return [
            'status' => 'accepted',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function rejectRequest(User $loggedInUser, User $requester): array
    {
        $relation = $this->findDirectRelation($requester, $loggedInUser);
        if (
            $relation === null ||
            (
                $relation->getStatus() !== FriendStatus::PENDING &&
                $relation->getStatus() !== FriendStatus::ACCEPTED
            )
        ) {
            throw new NotFoundHttpException('Pending or Accepted friend request not found.');
        }

        $relation->setStatus(FriendStatus::REJECTED);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($requester);

        return [
            'status' => 'rejected',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function cancelRequest(User $loggedInUser, User $targetUser): array
    {
        $this->guardNotSelf($loggedInUser, $targetUser);

        $relation = $this->findDirectRelation($loggedInUser, $targetUser);
        if ($relation === null || $relation->getStatus() !== FriendStatus::PENDING) {
            throw new NotFoundHttpException('Pending sent friend request not found.');
        }

        $this->entityManager->remove($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($targetUser);

        return [
            'status' => 'request_cancelled',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function block(User $loggedInUser, User $targetUser): array
    {
        $this->guardNotSelf($loggedInUser, $targetUser);

        $relation = $this->findRelation($loggedInUser, $targetUser);

        if ($relation === null) {
            $relation = (new UserFriendRelation())
                ->setRequester($loggedInUser)
                ->setAddressee($targetUser);
            $this->entityManager->persist($relation);
        }

        $relation->setStatus(FriendStatus::BLOCKED);
        $this->entityManager->persist($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($targetUser);

        return [
            'status' => 'blocked',
        ];
    }

    /**
     * @return array<string,string>
     */
    public function unblock(User $loggedInUser, User $targetUser): array
    {
        $relation = $this->findRelation($loggedInUser, $targetUser);

        if ($relation === null || $relation->getStatus() !== FriendStatus::BLOCKED) {
            throw new NotFoundHttpException('Blocked relation not found.');
        }

        $this->entityManager->remove($relation);
        $this->entityManager->flush();
        $this->invalidateUserCaches($loggedInUser);
        $this->invalidateUserCaches($targetUser);

        return [
            'status' => 'unblocked',
        ];
    }

    /**
     * @return array<int,array<string,string>>
     * @throws InvalidArgumentException
     */
    public function getMyFriends(User $loggedInUser): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r, requester, addressee')
            ->from(UserFriendRelation::class, 'r')
            ->join('r.requester', 'requester')
            ->join('r.addressee', 'addressee')
            ->where('(r.requester = :me OR r.addressee = :me)')
            ->andWhere('r.status = :status')
            ->setParameter('me', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('status', FriendStatus::ACCEPTED->value);

        $cacheKey = 'user_friends_accepted_' . $loggedInUser->getId();

        /** @var array<int,array<string,string>> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($qb, $loggedInUser): array {
            $item->expiresAfter(120);

            /** @var array<int,UserFriendRelation> $relations */
            $relations = $qb->getQuery()->getResult();

            return array_map(static function (UserFriendRelation $relation) use ($loggedInUser): array {
                $friend = $relation->getRequester()->getId() === $loggedInUser->getId()
                ? $relation->getAddressee()
                : $relation->getRequester();

                return [
                    'id' => $friend->getId(),
                    'username' => $friend->getUsername(),
                    'firstName' => $friend->getFirstName(),
                    'lastName' => $friend->getLastName(),
                    'photo' => $friend->getPhoto(),
                ];
            }, $relations);
        });

        return $payload;
    }

    /**
     * @return array<int,array<string,string>>
     * @throws InvalidArgumentException
     */
    public function getMyIncomingRequests(User $loggedInUser): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r, requester')
            ->from(UserFriendRelation::class, 'r')
            ->join('r.requester', 'requester')
            ->where('r.addressee = :me')
            ->andWhere('r.status = :status')
            ->setParameter('me', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('status', FriendStatus::PENDING->value);

        $cacheKey = 'user_friends_incoming_' . $loggedInUser->getId();

        /** @var array<int,array<string,string>> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($qb): array {
            $item->expiresAfter(120);

            /** @var array<int,UserFriendRelation> $relations */
            $relations = $qb->getQuery()->getResult();

            return array_map(static fn (UserFriendRelation $relation): array => [
                'id' => $relation->getRequester()->getId(),
                'username' => $relation->getRequester()->getUsername(),
                'firstName' => $relation->getRequester()->getFirstName(),
                'lastName' => $relation->getRequester()->getLastName(),
                'photo' => $relation->getRequester()->getPhoto(),
            ], $relations);
        });

        return $payload;
    }

    /**
     * @return array<int,array<string,string>>
     * @throws InvalidArgumentException
     */
    public function getMySentRequests(User $loggedInUser): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r, addressee')
            ->from(UserFriendRelation::class, 'r')
            ->join('r.addressee', 'addressee')
            ->where('r.requester = :me')
            ->andWhere('r.status = :status')
            ->setParameter('me', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('status', FriendStatus::PENDING->value);

        $cacheKey = 'user_friends_sent_' . $loggedInUser->getId();

        /** @var array<int,array<string,string>> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($qb): array {
            $item->expiresAfter(120);

            /** @var array<int,UserFriendRelation> $relations */
            $relations = $qb->getQuery()->getResult();

            return array_map(static fn (UserFriendRelation $relation): array => [
                'id' => $relation->getAddressee()->getId(),
                'username' => $relation->getAddressee()->getUsername(),
                'firstName' => $relation->getAddressee()->getFirstName(),
                'lastName' => $relation->getAddressee()->getLastName(),
                'photo' => $relation->getAddressee()->getPhoto(),
            ], $relations);
        });

        return $payload;
    }

    /**
     * @return array<int,array<string,string>>
     */
    public function getMyBlockedUsers(User $loggedInUser): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('r, addressee')
            ->from(UserFriendRelation::class, 'r')
            ->join('r.addressee', 'addressee')
            ->where('r.requester = :me')
            ->andWhere('r.status = :status')
            ->setParameter('me', $loggedInUser->getId(), UuidBinaryOrderedTimeType::NAME)
            ->setParameter('status', FriendStatus::BLOCKED->value);

        $cacheKey = 'user_friends_blocked_' . $loggedInUser->getId();

        /** @var array<int,array<string,string>> $payload */
        $payload = $this->cache->get($cacheKey, function (ItemInterface $item) use ($qb): array {
            $item->expiresAfter(120);

            /** @var array<int,UserFriendRelation> $relations */
            $relations = $qb->getQuery()->getResult();

            return array_map(static fn (UserFriendRelation $relation): array => [
                'id' => $relation->getAddressee()->getId(),
                'username' => $relation->getAddressee()->getUsername(),
                'firstName' => $relation->getAddressee()->getFirstName(),
                'lastName' => $relation->getAddressee()->getLastName(),
                'photo' => $relation->getAddressee()->getPhoto(),
            ], $relations);
        });

        return $payload;
    }

    private function buildUserProfileLink(User $user): string
    {
        return '/user/' . $user->getUsername() . '/profile';
    }

    private function invalidateUserCaches(User $user): void
    {
        $userId = $user->getId();
        $this->cache->delete('user_me_' . $userId);
        $this->cache->delete('user_sessions_' . $userId);
        $this->cache->delete('user_applications_' . $userId . '_0');
        $this->cache->delete('user_applications_' . $userId . '_3');
        $this->cache->delete('user_friends_accepted_' . $userId);
        $this->cache->delete('user_friends_incoming_' . $userId);
        $this->cache->delete('user_friends_sent_' . $userId);
        $this->cache->delete('user_friends_blocked_' . $userId);
    }

    private function guardNotSelf(User $loggedInUser, User $targetUser): void
    {
        if ($loggedInUser->getId() === $targetUser->getId()) {
            throw new BadRequestHttpException('You cannot perform this action on yourself.');
        }
    }

    private function findRelation(User $first, User $second): ?UserFriendRelation
    {
        $repository = $this->entityManager->getRepository(UserFriendRelation::class);

        /** @var UserFriendRelation|null $relation */
        $relation = $repository->findOneBy([
            'requester' => $first,
            'addressee' => $second,
        ]);

        if ($relation instanceof UserFriendRelation) {
            return $relation;
        }

        /** @var UserFriendRelation|null $reverse */
        $reverse = $repository->findOneBy([
            'requester' => $second,
            'addressee' => $first,
        ]);

        return $reverse;
    }

    private function findDirectRelation(User $requester, User $addressee): ?UserFriendRelation
    {
        $repository = $this->entityManager->getRepository(UserFriendRelation::class);

        /** @var UserFriendRelation|null $relation */
        $relation = $repository->findOneBy([
            'requester' => $requester,
            'addressee' => $addressee,
        ]);

        return $relation;
    }
}
