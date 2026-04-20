<?php

declare(strict_types=1);

namespace App\Calendar\Application\Service;

use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Calendar\Infrastructure\Repository\EventRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final readonly class GoogleCalendarSyncService
{
    private const string GOOGLE_CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';

    public function __construct(
        private EventRepository $eventRepository,
        private HttpClientInterface $httpClient,
    ) {
    }

    public function syncBidirectional(
        User $user,
        string $accessToken,
        string $calendarId = 'primary',
        ?DateTimeImmutable $timeMin = null,
        ?DateTimeImmutable $timeMax = null,
    ): array {
        if (!$timeMin instanceof DateTimeImmutable && !$timeMax instanceof DateTimeImmutable) {
            $timeMin = new DateTimeImmutable('first day of january this year 00:00:00', new DateTimeZone('UTC'));
            $timeMax = new DateTimeImmutable('last day of december this year 23:59:59', new DateTimeZone('UTC'));
        }

        $pulled = $this->pullGoogleEvents($user, $accessToken, $calendarId, $timeMin, $timeMax);

        $pushed = 0;
        $warnings = [];
        try {
            $pushed = $this->pushLocalEvents($user, $accessToken, $calendarId);
        } catch (HttpExceptionInterface $exception) {
            if (!$this->isInsufficientScopeError($exception)) {
                throw $exception;
            }

            $warnings[] = 'Google token does not include write scope. Local events were not pushed to Google Calendar.';
        }

        $result = [
            'pulledFromGoogle' => $pulled,
            'pushedToGoogle' => $pushed,
        ];

        if ($warnings !== []) {
            $result['warnings'] = $warnings;
        }

        return $result;
    }

    public function pushLocalEvent(Event $event): void
    {
        $metadata = $event->getMetadata() ?? [];
        $googleMetadata = $metadata['googleSync'] ?? null;
        if (!is_array($googleMetadata)) {
            return;
        }

        $accessToken = $googleMetadata['accessToken'] ?? null;
        if (!is_string($accessToken) || trim($accessToken) === '') {
            return;
        }

        $calendarId = $googleMetadata['calendarId'] ?? 'primary';
        if (!is_string($calendarId) || trim($calendarId) === '') {
            $calendarId = 'primary';
        }

        $this->upsertGoogleEvent($event, $accessToken, $calendarId);
    }

    public function deleteRemoteEvent(Event $event): void
    {
        $googleEventId = $event->getGoogleEventId();
        if (!is_string($googleEventId) || $googleEventId === '') {
            return;
        }

        $metadata = $event->getMetadata() ?? [];
        $googleMetadata = $metadata['googleSync'] ?? null;
        if (!is_array($googleMetadata)) {
            return;
        }

        $accessToken = $googleMetadata['accessToken'] ?? null;
        if (!is_string($accessToken) || trim($accessToken) === '') {
            return;
        }

        $calendarId = $event->getGoogleCalendarId() ?? 'primary';

        try {
            $this->requestGoogle(
                'DELETE',
                sprintf('/calendars/%s/events/%s', rawurlencode($calendarId), rawurlencode($googleEventId)),
                $accessToken,
            );
        } catch (Throwable) {
            // Ignore remote deletion errors to keep local delete resilient.
        }
    }

    private function pullGoogleEvents(User $user, string $accessToken, string $calendarId, ?DateTimeImmutable $timeMin, ?DateTimeImmutable $timeMax): int
    {
        $query = [
            'singleEvents' => 'true',
            'maxResults' => '2500',
            'orderBy' => 'updated',
        ];

        if ($timeMin instanceof DateTimeImmutable) {
            $query['timeMin'] = $timeMin->format(DATE_ATOM);
        }
        if ($timeMax instanceof DateTimeImmutable) {
            $query['timeMax'] = $timeMax->format(DATE_ATOM);
        }

        $response = $this->requestGoogle(
            'GET',
            sprintf('/calendars/%s/events', rawurlencode($calendarId)),
            $accessToken,
            ['query' => $query],
        );

        $items = $response['items'] ?? [];
        if (!is_array($items)) {
            return 0;
        }

        $count = 0;
        foreach ($items as $googleEvent) {
            if (!is_array($googleEvent)) {
                continue;
            }

            $googleEventId = (string)($googleEvent['id'] ?? '');
            if ($googleEventId === '') {
                continue;
            }

            $startAt = $this->parseGoogleDateTime($googleEvent['start']['dateTime'] ?? $googleEvent['start']['date'] ?? null);
            $endAt = $this->parseGoogleDateTime($googleEvent['end']['dateTime'] ?? $googleEvent['end']['date'] ?? null);
            if (!$startAt instanceof DateTimeImmutable || !$endAt instanceof DateTimeImmutable) {
                continue;
            }

            $event = $this->eventRepository->findOneByGoogleEventIdAndUserId($googleEventId, $user->getId());
            if (!$event instanceof Event) {
                $event = (new Event())->setUser($user);
            }

            $event
                ->setGoogleEventId($googleEventId)
                ->setGoogleCalendarId($calendarId)
                ->setTitle(trim((string)($googleEvent['summary'] ?? 'Untitled event')))
                ->setDescription((string)($googleEvent['description'] ?? ''))
                ->setStartAt($startAt)
                ->setEndAt($endAt)
                ->setLocation(isset($googleEvent['location']) ? (string)$googleEvent['location'] : null)
                ->setTimezone(isset($googleEvent['start']['timeZone']) ? (string)$googleEvent['start']['timeZone'] : null)
                ->setVisibility(EventVisibility::PRIVATE)
                ->setStatus(($googleEvent['status'] ?? '') === 'cancelled' ? EventStatus::CANCELLED : EventStatus::CONFIRMED)
                ->setIsCancelled(($googleEvent['status'] ?? '') === 'cancelled');

            $this->eventRepository->save($event);
            ++$count;
        }

        return $count;
    }

    private function pushLocalEvents(User $user, string $accessToken, string $calendarId): int
    {
        $events = $this->eventRepository->findByUser($user, [], 1, 500);
        $count = 0;
        foreach ($events as $event) {
            if (!$event instanceof Event) {
                continue;
            }

            if ($event->getCalendar()?->getApplication() !== null) {
                continue;
            }

            $this->upsertGoogleEvent($event, $accessToken, $calendarId);
            ++$count;
        }

        return $count;
    }

    private function upsertGoogleEvent(Event $event, string $accessToken, string $calendarId): void
    {
        $payload = [
            'summary' => $event->getTitle(),
            'description' => $event->getDescription(),
            'location' => $event->getLocation(),
            'status' => $event->isCancelled() ? 'cancelled' : 'confirmed',
            'start' => [
                'dateTime' => $event->getStartAt()->format(DATE_ATOM),
                'timeZone' => $event->getTimezone() ?? 'UTC',
            ],
            'end' => [
                'dateTime' => $event->getEndAt()->format(DATE_ATOM),
                'timeZone' => $event->getTimezone() ?? 'UTC',
            ],
        ];

        $googleEventId = $event->getGoogleEventId();
        $method = 'POST';
        $path = sprintf('/calendars/%s/events', rawurlencode($calendarId));

        if (is_string($googleEventId) && $googleEventId !== '') {
            $method = 'PATCH';
            $path = sprintf('/calendars/%s/events/%s', rawurlencode($calendarId), rawurlencode($googleEventId));
        }

        $response = $this->requestGoogle($method, $path, $accessToken, ['json' => $payload]);

        $remoteId = (string)($response['id'] ?? '');
        if ($remoteId !== '') {
            $event
                ->setGoogleEventId($remoteId)
                ->setGoogleCalendarId($calendarId);

            $metadata = $event->getMetadata() ?? [];
            $metadata['googleSync'] = [
                'calendarId' => $calendarId,
                'accessToken' => $accessToken,
            ];
            $event->setMetadata($metadata);

            $this->eventRepository->save($event);
        }
    }

    private function requestGoogle(string $method, string $path, string $accessToken, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, self::GOOGLE_CALENDAR_API_BASE . $path, $options + [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($accessToken),
                    'Accept' => 'application/json',
                ],
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (Throwable $exception) {
            throw new HttpException(JsonResponse::HTTP_BAD_GATEWAY, 'Google Calendar request failed.', $exception);
        }

        if ($status < 200 || $status >= 300) {
            $googleMessage = $this->extractGoogleErrorMessage($data);
            $message = 'Google Calendar request failed with status ' . $status . '.';
            if ($googleMessage !== null) {
                $message .= ' Google says: ' . $googleMessage;
            }

            $httpStatus = match ($status) {
                JsonResponse::HTTP_UNAUTHORIZED,
                JsonResponse::HTTP_FORBIDDEN,
                JsonResponse::HTTP_NOT_FOUND => $status,
                default => JsonResponse::HTTP_BAD_GATEWAY,
            };

            throw new HttpException($httpStatus, $message);
        }

        return is_array($data) ? $data : [];
    }

    private function extractGoogleErrorMessage(mixed $data): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        $error = $data['error'] ?? null;
        if (!is_array($error)) {
            return null;
        }

        $message = $error['message'] ?? null;
        if (is_string($message) && trim($message) !== '') {
            return trim($message);
        }

        return null;
    }

    private function isInsufficientScopeError(HttpExceptionInterface $exception): bool
    {
        if ($exception->getStatusCode() !== JsonResponse::HTTP_FORBIDDEN) {
            return false;
        }

        return str_contains(strtolower($exception->getMessage()), 'insufficient authentication scopes');
    }

    private function parseGoogleDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
