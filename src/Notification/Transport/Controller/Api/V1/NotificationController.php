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
    public function list(Request $request, User $loggedInUser): JsonResponse
    {
        $limit = max(1, (int) $request->query->get('limit', 50));
        $offset = max(0, (int) $request->query->get('offset', 0));

        $notifications = $this->notificationRepository->findByRecipient($loggedInUser, $limit, $offset);

        return new JsonResponse($this->notificationReadService->normalizeList($notifications));
    }

    #[Route('/v1/notifications/{id}', methods: [Request::METHOD_GET])]
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
