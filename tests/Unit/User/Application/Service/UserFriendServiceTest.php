<?php

declare(strict_types=1);

namespace App\Tests\Unit\User\Application\Service;

use App\User\Application\Service\UserFriendService;
use App\User\Domain\Entity\User;
use App\User\Domain\Entity\UserRelationship;
use App\User\Domain\Enum\UserRelationshipStatus;
use App\User\Domain\Repository\Interfaces\UserRelationshipRepositoryInterface;
use App\User\Infrastructure\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ForbiddenHttpException;

final class UserFriendServiceTest extends TestCase
{
    public function testRequestFriendRejectsSelfAction(): void
    {
        $loggedInUser = new User();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($loggedInUser);

        $relationshipRepository = $this->createMock(UserRelationshipRepositoryInterface::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new UserFriendService($userRepository, $relationshipRepository, $entityManager);

        $this->expectException(BadRequestHttpException::class);
        $service->requestFriend($loggedInUser->getId(), $loggedInUser);
    }

    public function testRequestFriendRejectsWhenBlockIsActive(): void
    {
        $loggedInUser = new User();
        $targetUser = new User();

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($targetUser);

        $relationshipRepository = $this->createMock(UserRelationshipRepositoryInterface::class);
        $relationshipRepository->expects(self::once())
            ->method('hasActiveBlock')
            ->with($loggedInUser, $targetUser)
            ->willReturn(true);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new UserFriendService($userRepository, $relationshipRepository, $entityManager);

        $this->expectException(ForbiddenHttpException::class);
        $service->requestFriend($targetUser->getId(), $loggedInUser);
    }

    public function testRequestFriendRejectsDuplicateOutgoingPendingRequest(): void
    {
        $loggedInUser = new User();
        $targetUser = new User();

        $relationship = (new UserRelationship())
            ->setRequester($loggedInUser)
            ->setAddressee($targetUser)
            ->setStatus(UserRelationshipStatus::PENDING);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($targetUser);

        $relationshipRepository = $this->createMock(UserRelationshipRepositoryInterface::class);
        $relationshipRepository->method('hasActiveBlock')->willReturn(false);
        $relationshipRepository->method('findRelationBetweenUsers')->willReturn($relationship);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new UserFriendService($userRepository, $relationshipRepository, $entityManager);

        $this->expectException(ConflictHttpException::class);
        $service->requestFriend($targetUser->getId(), $loggedInUser);
    }

    public function testAcceptFriendRequestIsForbiddenForNonAddressee(): void
    {
        $loggedInUser = new User();
        $targetUser = new User();

        $relationship = (new UserRelationship())
            ->setRequester($loggedInUser)
            ->setAddressee($targetUser)
            ->setStatus(UserRelationshipStatus::PENDING);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($targetUser);

        $relationshipRepository = $this->createMock(UserRelationshipRepositoryInterface::class);
        $relationshipRepository->method('hasActiveBlock')->willReturn(false);
        $relationshipRepository->method('findRelationBetweenUsers')->willReturn($relationship);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new UserFriendService($userRepository, $relationshipRepository, $entityManager);

        $this->expectException(ForbiddenHttpException::class);
        $service->acceptFriendRequest($targetUser->getId(), $loggedInUser);
    }

    public function testRejectFriendRequestWithInvalidStatusThrowsConflict(): void
    {
        $loggedInUser = new User();
        $targetUser = new User();

        $relationship = (new UserRelationship())
            ->setRequester($targetUser)
            ->setAddressee($loggedInUser)
            ->setStatus(UserRelationshipStatus::ACCEPTED);

        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->method('find')->willReturn($targetUser);

        $relationshipRepository = $this->createMock(UserRelationshipRepositoryInterface::class);
        $relationshipRepository->method('findRelationBetweenUsers')->willReturn($relationship);

        $entityManager = $this->createMock(EntityManagerInterface::class);

        $service = new UserFriendService($userRepository, $relationshipRepository, $entityManager);

        $this->expectException(ConflictHttpException::class);
        $service->rejectFriendRequest($targetUser->getId(), $loggedInUser);
    }
}
