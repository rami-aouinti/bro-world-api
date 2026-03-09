<?php

declare(strict_types=1);

namespace App\User\Application\Service;

use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserRelationship;
use App\User\Domain\Enum\UserRelationshipStatus;
use App\User\Domain\Repository\Interfaces\UserRelationshipRepositoryInterface;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ForbiddenHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class UserFriendService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserRelationshipRepositoryInterface $userRelationshipRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /** @return array<string,mixed> */
    public function requestFriend(string $targetUserId, User $loggedInUser): array
    {
        $targetUser = $this->getTargetUser($targetUserId);
        $this->assertNotSameUser($loggedInUser, $targetUser);
        $this->assertNoActiveBlock($loggedInUser, $targetUser, 'Cannot send a friend request while a block is active between both users.');

        $relationship = $this->userRelationshipRepository->findRelationBetweenUsers($loggedInUser, $targetUser);

        if ($relationship === null) {
            $relationship = new UserRelationship();
            $relationship
                ->setRequester($loggedInUser)
                ->setAddressee($targetUser)
                ->setStatus(UserRelationshipStatus::PENDING);

            $this->entityManager->persist($relationship);
            $this->entityManager->flush();

            return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
        }

        if ($relationship->getStatus() === UserRelationshipStatus::ACCEPTED) {
            throw new ConflictHttpException('You are already friends with this user.');
        }

        if ($relationship->getStatus() === UserRelationshipStatus::BLOCKED) {
            throw new ForbiddenHttpException('Cannot send a friend request while one of you has blocked the other.');
        }

        if ($relationship->getStatus() === UserRelationshipStatus::PENDING) {
            if ($relationship->getRequester() === $loggedInUser) {
                throw new ConflictHttpException('Friend request already sent.');
            }

            throw new ConflictHttpException('You already have an incoming pending friend request from this user.');
        }

        $relationship
            ->setRequester($loggedInUser)
            ->setAddressee($targetUser)
            ->setStatus(UserRelationshipStatus::PENDING);

        $this->entityManager->flush();

        return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
    }

    /** @return array<string,mixed> */
    public function acceptFriendRequest(string $targetUserId, User $loggedInUser): array
    {
        $targetUser = $this->getTargetUser($targetUserId);
        $this->assertNotSameUser($loggedInUser, $targetUser);
        $this->assertNoActiveBlock($loggedInUser, $targetUser, 'Cannot accept a friend request while a block is active between both users.');

        $relationship = $this->userRelationshipRepository->findRelationBetweenUsers($loggedInUser, $targetUser);

        if ($relationship === null) {
            throw new NotFoundHttpException('No friend request found between both users.');
        }

        if ($relationship->getStatus() !== UserRelationshipStatus::PENDING) {
            throw new ConflictHttpException('Cannot accept a friend request that is not pending.');
        }

        if ($relationship->getAddressee() !== $loggedInUser) {
            throw new ForbiddenHttpException('Only the addressee can accept this friend request.');
        }

        $relationship->setStatus(UserRelationshipStatus::ACCEPTED);
        $this->entityManager->flush();

        return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
    }

    /** @return array<string,mixed> */
    public function rejectFriendRequest(string $targetUserId, User $loggedInUser): array
    {
        $targetUser = $this->getTargetUser($targetUserId);
        $this->assertNotSameUser($loggedInUser, $targetUser);

        $relationship = $this->userRelationshipRepository->findRelationBetweenUsers($loggedInUser, $targetUser);

        if ($relationship === null) {
            throw new NotFoundHttpException('No friend request found between both users.');
        }

        if ($relationship->getStatus() !== UserRelationshipStatus::PENDING) {
            throw new ConflictHttpException('Cannot reject a friend request that is not pending.');
        }

        if ($relationship->getAddressee() !== $loggedInUser) {
            throw new ForbiddenHttpException('Only the addressee can reject this friend request.');
        }

        $relationship->setStatus(UserRelationshipStatus::REJECTED);
        $this->entityManager->flush();

        return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
    }

    /** @return array<string,mixed> */
    public function blockUser(string $targetUserId, User $loggedInUser): array
    {
        $targetUser = $this->getTargetUser($targetUserId);
        $this->assertNotSameUser($loggedInUser, $targetUser);

        $relationship = $this->userRelationshipRepository->findRelationBetweenUsers($loggedInUser, $targetUser);

        if ($relationship === null) {
            $relationship = new UserRelationship();
            $relationship
                ->setRequester($loggedInUser)
                ->setAddressee($targetUser);

            $this->entityManager->persist($relationship);
        }

        if ($relationship->getStatus() === UserRelationshipStatus::BLOCKED) {
            if ($relationship->getBlockedBy() === $loggedInUser) {
                throw new ConflictHttpException('This user is already blocked.');
            }

            throw new ForbiddenHttpException('Cannot override an active block created by the other user.');
        }

        $relationship
            ->setStatus(UserRelationshipStatus::BLOCKED)
            ->setBlockedBy($loggedInUser);

        $this->entityManager->flush();

        return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
    }

    /** @return array<string,mixed> */
    public function unblockUser(string $targetUserId, User $loggedInUser): array
    {
        $targetUser = $this->getTargetUser($targetUserId);
        $this->assertNotSameUser($loggedInUser, $targetUser);

        $relationship = $this->userRelationshipRepository->findRelationBetweenUsers($loggedInUser, $targetUser);

        if ($relationship === null || $relationship->getStatus() !== UserRelationshipStatus::BLOCKED) {
            throw new NotFoundHttpException('No block relationship found.');
        }

        if ($relationship->getBlockedBy() !== $loggedInUser) {
            throw new ForbiddenHttpException('Only the user who created the block can remove it.');
        }

        $relationship->setStatus(UserRelationshipStatus::REJECTED);
        $this->entityManager->flush();

        return ['status' => 'ok', 'data' => $this->normalizeRelationship($relationship)];
    }

    /** @return array<string,mixed> */
    public function getFriends(User $loggedInUser): array
    {
        $friends = $this->userRelationshipRepository->findAcceptedRelationships($loggedInUser);

        return [
            'status' => 'ok',
            'data' => [
                'friends' => array_map(fn (UserRelationship $relationship): array => $this->normalizeFriend($relationship, $loggedInUser), $friends),
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function getFriendRequests(User $loggedInUser): array
    {
        $incoming = $this->userRelationshipRepository->findIncomingRequests($loggedInUser);
        $outgoing = $this->userRelationshipRepository->findOutgoingRequests($loggedInUser);

        return [
            'status' => 'ok',
            'data' => [
                'incoming' => array_map(fn (UserRelationship $relationship): array => $this->normalizeRelationship($relationship), $incoming),
                'outgoing' => array_map(fn (UserRelationship $relationship): array => $this->normalizeRelationship($relationship), $outgoing),
            ],
        ];
    }

    private function getTargetUser(string $targetUserId): User
    {
        $targetUser = $this->userRepository->find($targetUserId);

        if (!$targetUser instanceof User) {
            throw new NotFoundHttpException('User not found.');
        }

        return $targetUser;
    }

    private function assertNotSameUser(User $loggedInUser, User $targetUser): void
    {
        if ($loggedInUser->getId() === $targetUser->getId()) {
            throw new BadRequestHttpException('Cannot perform this action on yourself.');
        }
    }

    private function assertNoActiveBlock(User $loggedInUser, User $targetUser, string $message): void
    {
        if ($this->userRelationshipRepository->hasActiveBlock($loggedInUser, $targetUser)) {
            throw new ForbiddenHttpException($message);
        }
    }

    /** @return array<string,mixed> */
    private function normalizeRelationship(UserRelationship $relationship): array
    {
        return [
            'id' => $relationship->getId(),
            'status' => $relationship->getStatus()->value,
            'requesterId' => $relationship->getRequester()->getId(),
            'addresseeId' => $relationship->getAddressee()->getId(),
            'blockedById' => $relationship->getBlockedBy()?->getId(),
            'createdAt' => $relationship->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $relationship->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function normalizeFriend(UserRelationship $relationship, User $loggedInUser): array
    {
        $friend = $relationship->getRequester() === $loggedInUser
            ? $relationship->getAddressee()
            : $relationship->getRequester();

        return [
            'id' => $friend->getId(),
            'username' => $friend->getUsername(),
            'firstName' => $friend->getFirstName(),
            'lastName' => $friend->getLastName(),
            'photo' => $friend->getPhoto(),
            'relationship' => $this->normalizeRelationship($relationship),
        ];
    }
}
