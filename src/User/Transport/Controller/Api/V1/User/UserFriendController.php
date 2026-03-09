<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\User;

use App\User\Application\Service\UserFriendService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'User Friends')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserFriendController
{
    public function __construct(private readonly UserFriendService $userFriendService)
    {
    }

    #[Route(path: '/v1/users/{id}/friends/request', methods: [Request::METHOD_POST])]
    public function requestFriend(string $id, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->requestFriend($id, $loggedInUser));
    }

    #[Route(path: '/v1/users/{id}/friends/accept', methods: [Request::METHOD_POST])]
    public function acceptFriend(string $id, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->acceptFriendRequest($id, $loggedInUser));
    }

    #[Route(path: '/v1/users/{id}/friends/reject', methods: [Request::METHOD_POST])]
    public function rejectFriend(string $id, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->rejectFriendRequest($id, $loggedInUser));
    }

    #[Route(path: '/v1/users/{id}/block', methods: [Request::METHOD_POST])]
    public function block(string $id, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->blockUser($id, $loggedInUser));
    }

    #[Route(path: '/v1/users/{id}/block', methods: [Request::METHOD_DELETE])]
    public function unblock(string $id, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->unblockUser($id, $loggedInUser));
    }

    #[Route(path: '/v1/users/me/friends', methods: [Request::METHOD_GET])]
    public function friends(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->getFriends($loggedInUser));
    }

    #[Route(path: '/v1/users/me/friends/requests', methods: [Request::METHOD_GET])]
    public function friendRequests(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->getFriendRequests($loggedInUser));
    }
}
