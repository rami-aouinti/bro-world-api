<?php

declare(strict_types=1);

namespace App\Calendar\Application\Service;

use App\Calendar\Domain\Enum\AttendeeResponseStatus;
use App\Calendar\Domain\Enum\ReminderMethod;
use App\General\Transport\Http\ValidationErrorFactory;

final class EventMutationPayloadValidator
{
    /**
     * @param mixed $value
     *
     * @return array<int, array{name: string, email: string, status: string}>|null
     */
    public function validateAttendees(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw ValidationErrorFactory::badRequest('Field "attendees" must be an array.');
        }

        $attendees = [];

        foreach ($value as $index => $attendee) {
            if (!is_array($attendee)) {
                throw ValidationErrorFactory::badRequest(sprintf('Field "attendees[%s]" must be an object.', (string) $index));
            }

            $name = $attendee['name'] ?? null;
            if (!is_string($name) || '' === trim($name)) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "attendees[%s].name" is required.', (string) $index));
            }

            $email = $attendee['email'] ?? null;
            if (!is_string($email) || false === filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "attendees[%s].email" must be a valid email.', (string) $index));
            }

            $status = $attendee['status'] ?? null;
            if (!is_string($status)) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "attendees[%s].status" is required.', (string) $index));
            }

            $parsedStatus = AttendeeResponseStatus::tryFrom($status);
            if ($parsedStatus === null) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "attendees[%s].status" must be one of: accepted, declined, tentative, needs_action.', (string) $index));
            }

            $attendees[] = [
                'name' => $name,
                'email' => $email,
                'status' => $parsedStatus->value,
            ];
        }

        return $attendees;
    }

    /**
     * @param mixed $value
     *
     * @return array<int, array{method: string, minutesBefore: int}>|null
     */
    public function validateReminders(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw ValidationErrorFactory::badRequest('Field "reminders" must be an array.');
        }

        $reminders = [];

        foreach ($value as $index => $reminder) {
            if (!is_array($reminder)) {
                throw ValidationErrorFactory::badRequest(sprintf('Field "reminders[%s]" must be an object.', (string) $index));
            }

            $method = $reminder['method'] ?? null;
            if (!is_string($method)) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "reminders[%s].method" is required.', (string) $index));
            }

            $parsedMethod = ReminderMethod::tryFrom($method);
            if ($parsedMethod === null) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "reminders[%s].method" must be one of: email, popup, sms.', (string) $index));
            }

            $minutesBefore = $reminder['minutesBefore'] ?? null;
            if (!is_int($minutesBefore) || $minutesBefore < 0) {
                throw ValidationErrorFactory::unprocessable(sprintf('Field "reminders[%s].minutesBefore" must be a positive integer or zero.', (string) $index));
            }

            $reminders[] = [
                'method' => $parsedMethod->value,
                'minutesBefore' => $minutesBefore,
            ];
        }

        return $reminders;
    }
}
