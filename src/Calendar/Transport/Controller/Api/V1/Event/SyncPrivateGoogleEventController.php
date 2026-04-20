<?php

declare(strict_types=1);

namespace App\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Service\GoogleCalendarSyncService;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Throwable;

#[AsController]
#[OA\Tag(name: 'Calendar Event')]
#[OA\Post(
    path: '/v1/calendar/private/events/google/sync',
    operationId: 'calendar_private_event_google_sync',
    summary: 'Synchronise bidirectionnelle avec Google Calendar',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['accessToken'],
            properties: [
                new OA\Property(property: 'accessToken', type: 'string', example: 'ya29.a0Af...'),
                new OA\Property(property: 'calendarId', type: 'string', example: 'primary'),
                new OA\Property(property: 'timeMin', type: 'string', format: 'date-time', example: '2026-01-01T00:00:00+00:00', nullable: true),
                new OA\Property(property: 'timeMax', type: 'string', format: 'date-time', example: '2026-12-31T23:59:59+00:00', nullable: true),
            ]
        )
    ),
    tags: ['Calendar Event'],
    responses: [
        new OA\Response(response: 200, description: 'Synchronisation terminée'),
        new OA\Response(response: 400, description: 'Payload invalide'),
        new OA\Response(response: 401, description: 'Token Google invalide ou expiré'),
        new OA\Response(response: 403, description: 'Permissions Google insuffisantes'),
        new OA\Response(response: 404, description: 'Calendrier Google introuvable'),
        new OA\Response(response: 502, description: 'Erreur Google Calendar'),
    ]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class SyncPrivateGoogleEventController
{
    public function __construct(
        public GoogleCalendarSyncService $googleCalendarSyncService,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(path: '/v1/calendar/private/events/google/sync', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();
        $accessToken = isset($payload['accessToken']) ? trim((string)$payload['accessToken']) : '';
        if ($accessToken === '') {
            throw new BadRequestHttpException('Field "accessToken" is required.');
        }

        $calendarId = isset($payload['calendarId']) && trim((string)$payload['calendarId']) !== ''
            ? trim((string)$payload['calendarId'])
            : 'primary';

        $timeMin = isset($payload['timeMin']) && trim((string)$payload['timeMin']) !== ''
            ? new DateTimeImmutable((string)$payload['timeMin'])
            : null;

        $timeMax = isset($payload['timeMax']) && trim((string)$payload['timeMax']) !== ''
            ? new DateTimeImmutable((string)$payload['timeMax'])
            : null;

        $result = $this->googleCalendarSyncService->syncBidirectional(
            user: $loggedInUser,
            accessToken: $accessToken,
            calendarId: $calendarId,
            timeMin: $timeMin,
            timeMax: $timeMax,
        );

        return new JsonResponse($result, JsonResponse::HTTP_OK);
    }
}
