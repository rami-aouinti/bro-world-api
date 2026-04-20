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
        bool $includeAllCalendars = false,
        bool $includeHolidays = false,
    ): array {
        if (!$timeMin instanceof DateTimeImmutable && !$timeMax instanceof DateTimeImmutable) {
            $timeMin = new DateTimeImmutable('first day of january this year 00:00:00', new DateTimeZone('UTC'));
        }

        $calendarsToSync = [
            [
                'id' => $calendarId,
                'summary' => $calendarId,
            ],
        ];

        if ($includeAllCalendars) {
            $calendarsToSync = $this->listGoogleCalendars($accessToken, $includeHolidays);
            if ($calendarsToSync === []) {
                $calendarsToSync = [
                    [
                        'id' => $calendarId,
                        'summary' => $calendarId,
                    ],
                ];
            }
        }

        $pulled = 0;
        foreach ($calendarsToSync as $calendarToSync) {
            $currentCalendarId = (string)($calendarToSync['id'] ?? '');
            if ($currentCalendarId === '') {
                continue;
            }

            $pulled += $this->pullGoogleEvents(
                $user,
                $accessToken,
                $currentCalendarId,
                $timeMin,
                $timeMax,
                $calendarToSync,
            );
        }

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
            'syncedCalendars' => array_values(array_map(static fn (array $calendar): string => (string)($calendar['id'] ?? ''), $calendarsToSync)),
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

    private function pullGoogleEvents(
        User $user,
        string $accessToken,
        string $calendarId,
        ?DateTimeImmutable $timeMin,
        ?DateTimeImmutable $timeMax,
        array $sourceCalendar = [],
    ): int
    {
        $baseQuery = [
            'singleEvents' => 'true',
            'maxResults' => '2500',
            'orderBy' => 'startTime',
        ];

        if ($timeMin instanceof DateTimeImmutable) {
            $baseQuery['timeMin'] = $timeMin->format(DATE_ATOM);
        }
        if ($timeMax instanceof DateTimeImmutable) {
            $baseQuery['timeMax'] = $timeMax->format(DATE_ATOM);
        }

        $count = 0;

        $pageToken = null;
        do {
            $query = $baseQuery;
            if (is_string($pageToken) && $pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->requestGoogle(
                'GET',
                sprintf('/calendars/%s/events', rawurlencode($calendarId)),
                $accessToken,
                ['query' => $query],
            );

            $items = $response['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }

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
                    ->setUrl(isset($googleEvent['htmlLink']) ? (string)$googleEvent['htmlLink'] : null)
                    ->setLocation(isset($googleEvent['location']) ? (string)$googleEvent['location'] : null)
                    ->setTimezone(isset($googleEvent['start']['timeZone']) ? (string)$googleEvent['start']['timeZone'] : null)
                    ->setIsAllDay(isset($googleEvent['start']['date']) && !isset($googleEvent['start']['dateTime']))
                    ->setColor(isset($googleEvent['colorId']) ? (string)$googleEvent['colorId'] : null)
                    ->setOrganizerName(isset($googleEvent['organizer']['displayName']) ? (string)$googleEvent['organizer']['displayName'] : null)
                    ->setOrganizerEmail(isset($googleEvent['organizer']['email']) ? (string)$googleEvent['organizer']['email'] : null)
                    ->setAttendees($this->normalizeGoogleAttendees($googleEvent['attendees'] ?? null))
                    ->setRrule($this->normalizeGoogleRecurrenceRule($googleEvent['recurrence'] ?? null))
                    ->setReminders($this->normalizeGoogleReminders($googleEvent['reminders'] ?? null))
                    ->setVisibility(EventVisibility::PRIVATE)
                    ->setStatus(($googleEvent['status'] ?? '') === 'cancelled' ? EventStatus::CANCELLED : EventStatus::CONFIRMED)
                    ->setIsCancelled(($googleEvent['status'] ?? '') === 'cancelled');

                $metadata = $event->getMetadata() ?? [];
                $metadata['googleSync'] = [
                    'calendarId' => $calendarId,
                    'accessToken' => $accessToken,
                ];
                $metadata['googleSourceCalendar'] = [
                    'id' => $calendarId,
                    'summary' => (string)($sourceCalendar['summary'] ?? ''),
                    'timeZone' => isset($sourceCalendar['timeZone']) ? (string)$sourceCalendar['timeZone'] : null,
                    'backgroundColor' => isset($sourceCalendar['backgroundColor']) ? (string)$sourceCalendar['backgroundColor'] : null,
                    'foregroundColor' => isset($sourceCalendar['foregroundColor']) ? (string)$sourceCalendar['foregroundColor'] : null,
                    'primary' => (bool)($sourceCalendar['primary'] ?? false),
                ];
                $metadata['googleEventType'] = (string)($googleEvent['eventType'] ?? 'default');
                $metadata['googleConferenceData'] = is_array($googleEvent['conferenceData'] ?? null) ? $googleEvent['conferenceData'] : null;
                $metadata['googleRawEvent'] = $googleEvent;
                $event->setMetadata($metadata);

                $this->eventRepository->save($event);
                ++$count;
            }

            $nextPageToken = $response['nextPageToken'] ?? null;
            $pageToken = is_string($nextPageToken) && trim($nextPageToken) !== ''
                ? trim($nextPageToken)
                : null;
        } while ($pageToken !== null);

        return $count;
    }

    /**
     * @return list<array{id:string,summary?:string,timeZone?:string,backgroundColor?:string,foregroundColor?:string,primary?:bool}>
     */
    private function listGoogleCalendars(string $accessToken, bool $includeHolidays): array
    {
        $calendars = [];
        $pageToken = null;

        do {
            $query = [
                'minAccessRole' => 'reader',
                'showHidden' => 'true',
                'showDeleted' => 'false',
            ];
            if (is_string($pageToken) && $pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            $response = $this->requestGoogle(
                'GET',
                '/users/me/calendarList',
                $accessToken,
                ['query' => $query],
            );

            $items = $response['items'] ?? [];
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $id = isset($item['id']) ? trim((string)$item['id']) : '';
                    if ($id === '') {
                        continue;
                    }

                    if (!$includeHolidays && $this->isHolidayCalendarId($id)) {
                        continue;
                    }

                    $calendars[] = [
                        'id' => $id,
                        'summary' => isset($item['summary']) ? (string)$item['summary'] : null,
                        'timeZone' => isset($item['timeZone']) ? (string)$item['timeZone'] : null,
                        'backgroundColor' => isset($item['backgroundColor']) ? (string)$item['backgroundColor'] : null,
                        'foregroundColor' => isset($item['foregroundColor']) ? (string)$item['foregroundColor'] : null,
                        'primary' => (bool)($item['primary'] ?? false),
                    ];
                }
            }

            $nextPageToken = $response['nextPageToken'] ?? null;
            $pageToken = is_string($nextPageToken) && trim($nextPageToken) !== ''
                ? trim($nextPageToken)
                : null;
        } while ($pageToken !== null);

        return $calendars;
    }

    private function isHolidayCalendarId(string $calendarId): bool
    {
        $id = strtolower($calendarId);

        return str_contains($id, 'holiday')
            || str_contains($id, '#holiday@group.v.calendar.google.com')
            || str_contains($id, 'group.v.calendar.google.com');
    }

    /**
     * @return list<array<string, mixed>>|null
     */
    private function normalizeGoogleAttendees(mixed $attendees): ?array
    {
        if (!is_array($attendees)) {
            return null;
        }

        $normalized = [];
        foreach ($attendees as $attendee) {
            if (!is_array($attendee)) {
                continue;
            }

            $normalized[] = [
                'email' => isset($attendee['email']) ? (string)$attendee['email'] : null,
                'displayName' => isset($attendee['displayName']) ? (string)$attendee['displayName'] : null,
                'responseStatus' => isset($attendee['responseStatus']) ? (string)$attendee['responseStatus'] : null,
                'optional' => (bool)($attendee['optional'] ?? false),
                'organizer' => (bool)($attendee['organizer'] ?? false),
                'self' => (bool)($attendee['self'] ?? false),
                'resource' => (bool)($attendee['resource'] ?? false),
            ];
        }

        return $normalized === [] ? null : $normalized;
    }

    private function normalizeGoogleRecurrenceRule(mixed $recurrence): ?string
    {
        if (!is_array($recurrence)) {
            return null;
        }

        $rules = array_values(array_filter(array_map(static function (mixed $item): ?string {
            if (!is_string($item)) {
                return null;
            }

            $rule = trim($item);

            return $rule !== '' ? $rule : null;
        }, $recurrence)));

        if ($rules === []) {
            return null;
        }

        return implode("\n", $rules);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeGoogleReminders(mixed $reminders): ?array
    {
        if (!is_array($reminders)) {
            return null;
        }

        return [
            'useDefault' => (bool)($reminders['useDefault'] ?? false),
            'overrides' => is_array($reminders['overrides'] ?? null) ? $reminders['overrides'] : [],
        ];
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
