<?php

declare(strict_types=1);

namespace App\Notification\Transport\Controller\Api\V1;

use App\Notification\Application\Service\NotificationReadService;
use App\Notification\Domain\Entity\Notification;
use App\Notification\Infrastructure\Repository\NotificationRepository;
use App\User\Domain\Entity\User;
use App\User\Infrastructure\Repository\UserRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function trim;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Notification')]
final readonly class NotificationController
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private UserRepository $userRepository,
        private NotificationReadService $notificationReadService,
    ) {}

    #[Route('/v1/notifications', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List notifications for the logged-in user.')]
    #[OA\Parameter(name: 'limit', description: 'Max number of items to return (default: 50).', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, example: 20))]
    #[OA\Parameter(name: 'offset', description: 'Pagination offset (default: 0).', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 0, example: 0))]
    #[OA\Response(
        response: 200,
        description: 'Notifications list.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'title', type: 'string', example: 'System maintenance'),
                    new OA\Property(property: 'description', type: 'string', example: 'A maintenance window is planned for tonight.'),
                    new OA\Property(property: 'type', type: 'string', example: 'system'),
                    new OA\Property(property: 'read', type: 'boolean', example: false),
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time'),
                    new OA\Property(
                        property: 'from',
                        nullable: true,
                        oneOf: [
                            new OA\Schema(type: 'null', example: null),
                            new OA\Schema(
                                properties: [
                                    new OA\Property(property: 'firstName', type: 'string', example: 'John'),
                                    new OA\Property(property: 'lastName', type: 'string', example: 'Doe'),
                                    new OA\Property(property: 'photo', type: 'string', nullable: true, example: '/uploads/profile/avatar.jpg'),
                                ],
                                type: 'object',
                            ),
                        ],
                    ),
                ],
                type: 'object',
            ),
            example: [
                [
                    'id' => '70000000-0000-1000-8000-000000000001',
                    'title' => 'System maintenance',
                    'description' => 'A maintenance window is planned for tonight.',
                    'type' => 'system',
                    'read' => false,
                    'createdAt' => '2026-03-10T10:00:00+00:00',
                    'from' => null,
                ],
                [
                    'id' => '70000000-0000-1000-8000-000000000002',
                    'title' => 'Profile warning',
                    'description' => 'Your profile is missing a required document.',
                    'type' => 'warning',
                    'read' => false,
                    'createdAt' => '2026-03-10T10:05:00+00:00',
                    'from' => [
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'photo' => '/uploads/profile/avatar.jpg',
                    ],
                ],
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid query parameters.')]
    #[OA\Response(response: 401, description: 'Authentication required.')]
    #[OA\Response(response: 403, description: 'Access denied.')]
    public function list(Request $request, User $loggedInUser): JsonResponse
    {
        $limit = max(1, (int) $request->query->get('limit', 50));
        $offset = max(0, (int) $request->query->get('offset', 0));

        $notifications = $this->notificationRepository->findByRecipient($loggedInUser, $limit, $offset);

        return new JsonResponse([
            'items' => $this->notificationReadService->normalizeList($notifications),
            'unreadCount' => $this->notificationRepository->countUnreadByRecipient($loggedInUser),
        ]);
    }

    #[Route('/v1/notifications/read-all', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'Mark all notifications as read for the logged-in user.')]
    #[OA\Response(
        response: 200,
        description: 'Notifications updated.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'updatedCount', type: 'integer', example: 3),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(response: 401, description: 'Authentication required.')]
    #[OA\Response(response: 403, description: 'Access denied.')]
    public function markAllAsRead(User $loggedInUser): JsonResponse
    {
        $updatedCount = $this->notificationRepository->markAllAsReadByRecipient($loggedInUser);

        return new JsonResponse(['updatedCount' => $updatedCount]);
    }


    #[Route('/v1/notifications/{id}', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Get a notification detail by id.')]
    #[OA\Parameter(name: 'id', description: 'Notification UUID.', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: 200, description: 'Notification detail.')]
    #[OA\Response(response: 401, description: 'Authentication required.')]
    #[OA\Response(response: 403, description: 'You cannot access this notification.')]
    #[OA\Response(response: 404, description: 'Notification not found.')]
    public function detail(string $id, User $loggedInUser): JsonResponse
    {
        $notification = $this->notificationRepository->find($id);
        if (!$notification instanceof Notification) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Notification not found.');
        }

        if ($notification->getRecipient()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this notification.');
        }

        return new JsonResponse($this->notificationReadService->normalize($notification));
    }

    #[Route('/v1/notifications', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Create a notification.')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'type', 'toId'],
            properties: [
                new OA\Property(property: 'title', type: 'string', example: 'System maintenance'),
                new OA\Property(property: 'description', type: 'string', example: 'A maintenance window is planned for tonight.'),
                new OA\Property(property: 'type', type: 'string', example: 'system'),
                new OA\Property(property: 'toId', type: 'string', format: 'uuid', example: '20000000-0000-1000-8000-000000000004'),
                new OA\Property(property: 'recipientId', type: 'string', format: 'uuid', example: null, nullable: true),
                new OA\Property(property: 'fromId', type: 'string', format: 'uuid', example: '20000000-0000-1000-8000-000000000006', nullable: true),
            ],
            example: [
                'title' => 'Profile warning',
                'description' => 'Your profile is missing a required document.',
                'type' => 'warning',
                'toId' => '20000000-0000-1000-8000-000000000005',
                'fromId' => '20000000-0000-1000-8000-000000000006',
            ],
        ),
    )]
    #[OA\Response(response: 201, description: 'Notification created.')]
    #[OA\Response(response: 400, description: 'Validation error or unknown users.')]
    #[OA\Response(response: 401, description: 'Authentication required.')]
    #[OA\Response(response: 403, description: 'Access denied.')]
    #[OA\Response(response: 404, description: 'Related resource not found.')]
    public function create(Request $request): JsonResponse
    {
        $payload = $request->toArray();

        $title = $payload['title'] ?? null;
        $description = $payload['description'] ?? '';
        $type = $payload['type'] ?? null;
        $toId = $payload['toId'] ?? $payload['recipientId'] ?? null;
        $fromId = $payload['fromId'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" must be a non-empty string.');
        }

        if (!is_string($description)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "description" must be a string.');
        }

        if (!is_string($type) || trim($type) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "type" must be a non-empty string.');
        }

        if (!is_string($toId) || trim($toId) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "toId" (or "recipientId") is required and must be a non-empty string.');
        }

        $recipient = $this->userRepository->find($toId);
        if (!$recipient instanceof User) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Recipient user not found.');
        }

        $from = null;
        if ($fromId !== null) {
            if (!is_string($fromId) || trim($fromId) === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "fromId" must be a non-empty string when provided.');
            }

            $from = $this->userRepository->find($fromId);
            if (!$from instanceof User) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Sender user not found.');
            }
        }

        $notification = (new Notification())
            ->setTitle(trim($title))
            ->setDescription(trim($description))
            ->setType(trim($type))
            ->setRecipient($recipient)
            ->setFrom($from);

        $this->notificationRepository->save($notification);

        return new JsonResponse($this->notificationReadService->normalize($notification), JsonResponse::HTTP_CREATED);
    }
}
