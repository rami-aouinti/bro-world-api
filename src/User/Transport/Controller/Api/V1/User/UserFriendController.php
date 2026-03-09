<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\User;

use App\User\Application\Service\UserFriendService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Property;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
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

    #[Route(path: '/v1/users/{user}/friends/request', requirements: ['user' => Requirement::UUID_V1], methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'user', description: 'Target user UUID', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Request sent', content: new JsonContent(example: ['status' => 'request_sent']))]
    public function sendRequest(User $user, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->sendRequest($loggedInUser, $user));
    }

    #[Route(path: '/v1/users/{user}/friends/accept', requirements: ['user' => Requirement::UUID_V1], methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'user', description: 'Requester user UUID', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Request accepted', content: new JsonContent(example: ['status' => 'accepted']))]
    public function acceptRequest(User $user, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->acceptRequest($loggedInUser, $user));
    }

    #[Route(path: '/v1/users/{user}/friends/reject', requirements: ['user' => Requirement::UUID_V1], methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'user', description: 'Requester user UUID', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'Request rejected', content: new JsonContent(example: ['status' => 'rejected']))]
    public function rejectRequest(User $user, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->rejectRequest($loggedInUser, $user));
    }

    #[Route(path: '/v1/users/{user}/block', requirements: ['user' => Requirement::UUID_V1], methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'user', description: 'Target user UUID', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'User blocked', content: new JsonContent(example: ['status' => 'blocked']))]
    public function block(User $user, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->block($loggedInUser, $user));
    }

    #[Route(path: '/v1/users/{user}/block', requirements: ['user' => Requirement::UUID_V1], methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'user', description: 'Target user UUID', in: 'path', required: true)]
    #[OA\Response(response: 200, description: 'User unblocked', content: new JsonContent(example: ['status' => 'unblocked']))]
    public function unblock(User $user, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->unblock($loggedInUser, $user));
    }

    #[Route(path: '/v1/users/me/friends', methods: [Request::METHOD_GET])]
    #[OA\Response(
        response: 200,
        description: 'My accepted friends',
        content: new JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new Property(property: 'id', type: 'string'),
                    new Property(property: 'username', type: 'string'),
                    new Property(property: 'firstName', type: 'string'),
                    new Property(property: 'lastName', type: 'string'),
                ],
                type: 'object',
            ),
        ),
    )]
    public function myFriends(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->getMyFriends($loggedInUser));
    }

    #[Route(path: '/v1/users/me/friends/requests', methods: [Request::METHOD_GET])]
    #[OA\Response(
        response: 200,
        description: 'My incoming pending requests',
        content: new JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new Property(property: 'id', type: 'string'),
                    new Property(property: 'username', type: 'string'),
                    new Property(property: 'firstName', type: 'string'),
                    new Property(property: 'lastName', type: 'string'),
                ],
                type: 'object',
            ),
        ),
    )]
    public function myIncomingRequests(User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->userFriendService->getMyIncomingRequests($loggedInUser));
    }
}
