<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserFriendRelation;
use App\User\Domain\Enum\FriendStatus;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class UserFriendService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string,string> */
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

        return ['status' => 'request_sent'];
    }

    /** @return array<string,string> */
    public function acceptRequest(User $loggedInUser, User $requester): array
    {
        $relation = $this->findDirectRelation($requester, $loggedInUser);
        if ($relation === null || $relation->getStatus() !== FriendStatus::PENDING) {
            throw new NotFoundHttpException('Pending friend request not found.');
        }

        $relation->setStatus(FriendStatus::ACCEPTED);
        $this->entityManager->flush();

        return ['status' => 'accepted'];
    }

    /** @return array<string,string> */
    public function rejectRequest(User $loggedInUser, User $requester): array
    {
        $relation = $this->findDirectRelation($requester, $loggedInUser);
        if ($relation === null || $relation->getStatus() !== FriendStatus::PENDING) {
            throw new NotFoundHttpException('Pending friend request not found.');
        }

        $relation->setStatus(FriendStatus::REJECTED);
        $this->entityManager->flush();

        return ['status' => 'rejected'];
    }

    /** @return array<string,string> */
    public function cancelRequest(User $loggedInUser, User $targetUser): array
    {
        $this->guardNotSelf($loggedInUser, $targetUser);

        $relation = $this->findDirectRelation($loggedInUser, $targetUser);
        if ($relation === null || $relation->getStatus() !== FriendStatus::PENDING) {
            throw new NotFoundHttpException('Pending sent friend request not found.');
        }

        $this->entityManager->remove($relation);
        $this->entityManager->flush();

        return ['status' => 'request_cancelled'];
    }

    /** @return array<string,string> */
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
        $this->entityManager->flush();

        return ['status' => 'blocked'];
    }

    /** @return array<string,string> */
    public function unblock(User $loggedInUser, User $targetUser): array
    {
        $relation = $this->findRelation($loggedInUser, $targetUser);

        if ($relation === null || $relation->getStatus() !== FriendStatus::BLOCKED) {
            throw new NotFoundHttpException('Blocked relation not found.');
        }

        $this->entityManager->remove($relation);
        $this->entityManager->flush();

        return ['status' => 'unblocked'];
    }

    /** @return array<int,array<string,string>> */
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
                'photo' => $relation->getRequester()->getPhoto(),
            ];
        }, $relations);
    }

    /** @return array<int,array<string,string>> */
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

        /** @var array<int,UserFriendRelation> $relations */
        $relations = $qb->getQuery()->getResult();

        return array_map(static fn (UserFriendRelation $relation): array => [
            'id' => $relation->getRequester()->getId(),
            'username' => $relation->getRequester()->getUsername(),
            'firstName' => $relation->getRequester()->getFirstName(),
            'lastName' => $relation->getRequester()->getLastName(),
            'photo' => $relation->getRequester()->getPhoto(),
        ], $relations);
    }

    /** @return array<int,array<string,string>> */
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

        /** @var array<int,UserFriendRelation> $relations */
        $relations = $qb->getQuery()->getResult();

        return array_map(static fn (UserFriendRelation $relation): array => [
            'id' => $relation->getAddressee()->getId(),
            'username' => $relation->getAddressee()->getUsername(),
            'firstName' => $relation->getAddressee()->getFirstName(),
            'lastName' => $relation->getAddressee()->getLastName(),
            'photo' => $relation->getAddressee()->getPhoto(),
        ], $relations);
    }

    /** @return array<int,array<string,string>> */
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

        /** @var array<int,UserFriendRelation> $relations */
        $relations = $qb->getQuery()->getResult();

        return array_map(static fn (UserFriendRelation $relation): array => [
            'id' => $relation->getAddressee()->getId(),
            'username' => $relation->getAddressee()->getUsername(),
            'firstName' => $relation->getAddressee()->getFirstName(),
            'lastName' => $relation->getAddressee()->getLastName(),
            'photo' => $relation->getAddressee()->getPhoto(),
        ], $relations);
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
        $relation = $repository->findOneBy(['requester' => $first, 'addressee' => $second]);

        if ($relation instanceof UserFriendRelation) {
            return $relation;
        }

        /** @var UserFriendRelation|null $reverse */
        $reverse = $repository->findOneBy(['requester' => $second, 'addressee' => $first]);

        return $reverse;
    }

    private function findDirectRelation(User $requester, User $addressee): ?UserFriendRelation
    {
        $repository = $this->entityManager->getRepository(UserFriendRelation::class);

        /** @var UserFriendRelation|null $relation */
        $relation = $repository->findOneBy(['requester' => $requester, 'addressee' => $addressee]);

        return $relation;
    }
}
