<?php

declare(strict_types=1);

namespace App\Calendar\Application\Service;

use App\Calendar\Application\Message\CancelEventCommand;
use App\Calendar\Application\Message\CreateEventCommand;
use App\Calendar\Application\Message\DeleteEventCommand;
use App\Calendar\Application\Message\PatchEventCommand;
use App\Calendar\Domain\Enum\EventStatus;
use App\Calendar\Domain\Enum\EventVisibility;
use App\General\Transport\Http\ValidationErrorFactory;
use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class EventMutationInputFactory
{
    public function __construct(
        private readonly EventMutationPayloadValidator $payloadValidator,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createPrivateCreateCommand(array $payload, string $actorUserId): CreateEventCommand
    {
        return $this->buildCreateCommand($payload, $actorUserId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createApplicationCreateCommand(array $payload, string $actorUserId, string $applicationSlug): CreateEventCommand
    {
        return $this->buildCreateCommand($payload, $actorUserId, $applicationSlug);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createPrivatePatchCommand(string $eventId, array $payload, string $actorUserId): PatchEventCommand
    {
        return $this->buildPatchCommand($eventId, $payload, $actorUserId);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function createApplicationPatchCommand(string $applicationSlug, string $eventId, array $payload, string $actorUserId): PatchEventCommand
    {
        return $this->buildPatchCommand($eventId, $payload, $actorUserId, $applicationSlug);
    }

    public function createPrivateDeleteCommand(string $eventId, string $actorUserId): DeleteEventCommand
    {
        return $this->buildDeleteCommand($eventId, $actorUserId);
    }

    public function createApplicationDeleteCommand(string $applicationSlug, string $eventId, string $actorUserId): DeleteEventCommand
    {
        return $this->buildDeleteCommand($eventId, $actorUserId, $applicationSlug);
    }

    public function createPrivateCancelCommand(string $eventId, string $actorUserId): CancelEventCommand
    {
        return $this->buildCancelCommand($eventId, $actorUserId);
    }

    public function createApplicationCancelCommand(string $applicationSlug, string $eventId, string $actorUserId): CancelEventCommand
    {
        return $this->buildCancelCommand($eventId, $actorUserId, $applicationSlug);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildCreateCommand(array $payload, string $actorUserId, ?string $applicationSlug = null): CreateEventCommand
    {
        $this->assertApplicationSlug($applicationSlug);

        $startAt = $this->requireDate($payload, 'startAt');
        $endAt = $this->requireDate($payload, 'endAt');
        $this->assertDateRange($startAt, $endAt);

        return new CreateEventCommand(
            operationId: Uuid::uuid4()->toString(),
            actorUserId: $actorUserId,
            title: $this->requireString($payload, 'title'),
            description: (string) ($payload['description'] ?? ''),
            startAt: $startAt,
            endAt: $endAt,
            status: $this->parseStatus((string) ($payload['status'] ?? EventStatus::CONFIRMED->value)),
            visibility: $this->parseVisibility((string) ($payload['visibility'] ?? EventVisibility::PRIVATE->value)),
            location: isset($payload['location']) && is_string($payload['location']) ? $payload['location'] : null,
            isAllDay: isset($payload['isAllDay']) && is_bool($payload['isAllDay']) ? $payload['isAllDay'] : false,
            timezone: isset($payload['timezone']) && is_string($payload['timezone']) ? $payload['timezone'] : null,
            url: isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null,
            color: isset($payload['color']) && is_string($payload['color']) ? $payload['color'] : null,
            backgroundColor: isset($payload['backgroundColor']) && is_string($payload['backgroundColor']) ? $payload['backgroundColor'] : null,
            borderColor: isset($payload['borderColor']) && is_string($payload['borderColor']) ? $payload['borderColor'] : null,
            textColor: isset($payload['textColor']) && is_string($payload['textColor']) ? $payload['textColor'] : null,
            organizerName: isset($payload['organizerName']) && is_string($payload['organizerName']) ? $payload['organizerName'] : null,
            organizerEmail: isset($payload['organizerEmail']) && is_string($payload['organizerEmail']) ? $payload['organizerEmail'] : null,
            attendees: $this->payloadValidator->validateAttendees($payload['attendees'] ?? null),
            rrule: isset($payload['rrule']) && is_string($payload['rrule']) ? $payload['rrule'] : null,
            recurrenceExceptions: isset($payload['recurrenceExceptions']) && is_array($payload['recurrenceExceptions']) ? $payload['recurrenceExceptions'] : null,
            recurrenceEndAt: isset($payload['recurrenceEndAt']) && is_string($payload['recurrenceEndAt']) ? $this->parseDate($payload['recurrenceEndAt'], 'recurrenceEndAt') : null,
            recurrenceCount: isset($payload['recurrenceCount']) && is_int($payload['recurrenceCount']) ? $payload['recurrenceCount'] : null,
            reminders: $this->payloadValidator->validateReminders($payload['reminders'] ?? null),
            metadata: isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null,
            applicationSlug: $applicationSlug,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildPatchCommand(string $eventId, array $payload, string $actorUserId, ?string $applicationSlug = null): PatchEventCommand
    {
        $this->assertApplicationSlug($applicationSlug);
        $this->assertUuid($eventId, 'eventId');

        $startAt = isset($payload['startAt']) && is_string($payload['startAt']) ? $this->parseDate($payload['startAt'], 'startAt') : null;
        $endAt = isset($payload['endAt']) && is_string($payload['endAt']) ? $this->parseDate($payload['endAt'], 'endAt') : null;

        if ($startAt !== null && $endAt !== null) {
            $this->assertDateRange($startAt, $endAt);
        }

        return new PatchEventCommand(
            operationId: Uuid::uuid4()->toString(),
            actorUserId: $actorUserId,
            eventId: $eventId,
            title: isset($payload['title']) && is_string($payload['title']) ? $payload['title'] : null,
            description: isset($payload['description']) && is_string($payload['description']) ? $payload['description'] : null,
            startAt: $startAt,
            endAt: $endAt,
            visibility: isset($payload['visibility']) && is_string($payload['visibility']) ? $this->parseVisibility($payload['visibility']) : null,
            location: isset($payload['location']) && is_string($payload['location']) ? $payload['location'] : null,
            isAllDay: isset($payload['isAllDay']) && is_bool($payload['isAllDay']) ? $payload['isAllDay'] : null,
            timezone: isset($payload['timezone']) && is_string($payload['timezone']) ? $payload['timezone'] : null,
            url: isset($payload['url']) && is_string($payload['url']) ? $payload['url'] : null,
            color: isset($payload['color']) && is_string($payload['color']) ? $payload['color'] : null,
            backgroundColor: isset($payload['backgroundColor']) && is_string($payload['backgroundColor']) ? $payload['backgroundColor'] : null,
            borderColor: isset($payload['borderColor']) && is_string($payload['borderColor']) ? $payload['borderColor'] : null,
            textColor: isset($payload['textColor']) && is_string($payload['textColor']) ? $payload['textColor'] : null,
            organizerName: isset($payload['organizerName']) && is_string($payload['organizerName']) ? $payload['organizerName'] : null,
            organizerEmail: isset($payload['organizerEmail']) && is_string($payload['organizerEmail']) ? $payload['organizerEmail'] : null,
            attendees: $this->payloadValidator->validateAttendees($payload['attendees'] ?? null),
            rrule: isset($payload['rrule']) && is_string($payload['rrule']) ? $payload['rrule'] : null,
            recurrenceExceptions: isset($payload['recurrenceExceptions']) && is_array($payload['recurrenceExceptions']) ? $payload['recurrenceExceptions'] : null,
            recurrenceEndAt: isset($payload['recurrenceEndAt']) && is_string($payload['recurrenceEndAt']) ? $this->parseDate($payload['recurrenceEndAt'], 'recurrenceEndAt') : null,
            recurrenceCount: isset($payload['recurrenceCount']) && is_int($payload['recurrenceCount']) ? $payload['recurrenceCount'] : null,
            reminders: $this->payloadValidator->validateReminders($payload['reminders'] ?? null),
            metadata: isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : null,
            applicationSlug: $applicationSlug,
        );
    }

    private function buildDeleteCommand(string $eventId, string $actorUserId, ?string $applicationSlug = null): DeleteEventCommand
    {
        $this->assertApplicationSlug($applicationSlug);
        $this->assertUuid($eventId, 'eventId');

        return new DeleteEventCommand(
            operationId: Uuid::uuid4()->toString(),
            actorUserId: $actorUserId,
            eventId: $eventId,
            applicationSlug: $applicationSlug,
        );
    }

    private function buildCancelCommand(string $eventId, string $actorUserId, ?string $applicationSlug = null): CancelEventCommand
    {
        $this->assertApplicationSlug($applicationSlug);
        $this->assertUuid($eventId, 'eventId');

        return new CancelEventCommand(
            operationId: Uuid::uuid4()->toString(),
            actorUserId: $actorUserId,
            eventId: $eventId,
            applicationSlug: $applicationSlug,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requireString(array $payload, string $field): string
    {
        $value = $payload[$field] ?? null;
        if (!is_string($value) || $value === '') {
            throw ValidationErrorFactory::badRequest('Field "' . $field . '" is required.');
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function requireDate(array $payload, string $field): DateTimeImmutable
    {
        $value = $payload[$field] ?? null;
        if (!is_string($value)) {
            throw ValidationErrorFactory::badRequest('Field "' . $field . '" must be a valid date string.');
        }

        return $this->parseDate($value, $field);
    }

    private function parseDate(string $value, string $field): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw ValidationErrorFactory::badRequest('Field "' . $field . '" must be a valid date string.');
        }
    }

    private function parseStatus(string $status): EventStatus
    {
        return EventStatus::tryFrom($status) ?? throw ValidationErrorFactory::unprocessable('Invalid event status.');
    }

    private function parseVisibility(string $visibility): EventVisibility
    {
        return EventVisibility::tryFrom($visibility) ?? throw ValidationErrorFactory::unprocessable('Invalid event visibility.');
    }

    private function assertApplicationSlug(?string $applicationSlug): void
    {
        if ($applicationSlug !== null && $applicationSlug === '') {
            throw ValidationErrorFactory::badRequest('Field "applicationSlug" is required.');
        }
    }

    private function assertUuid(string $value, string $field): void
    {
        if (!Uuid::isValid($value)) {
            throw ValidationErrorFactory::badRequest('Field "' . $field . '" must be a valid UUID.');
        }
    }

    private function assertDateRange(DateTimeImmutable $startAt, DateTimeImmutable $endAt): void
    {
        if ($endAt < $startAt) {
            throw ValidationErrorFactory::unprocessable('Field "endAt" must be greater than or equal to "startAt".');
        }
    }
}
