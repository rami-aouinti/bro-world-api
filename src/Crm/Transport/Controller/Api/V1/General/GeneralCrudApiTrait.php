<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Billing;
use App\Crm\Domain\Entity\TaskRequest;
use DateTimeImmutable;
use DateTimeInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function is_array;
use function is_string;

trait GeneralCrudApiTrait
{
    /** @return array<string,mixed>|JsonResponse */
    private function decodePayload(Request $request): array|JsonResponse
    {
        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->badRequest('Invalid JSON payload.');
        }

        if (!is_array($payload)) {
            return $this->badRequest('Invalid JSON payload.');
        }

        return $payload;
    }

    private function parseDate(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);
        if ($date === false) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid ISO 8601 date format.');
        }

        return $date;
    }

    private function parseNullableDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Date must be a string or null.');
        }

        return $this->parseDate($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    private function badRequest(string $message): JsonResponse
    {
        return new JsonResponse([
            'message' => $message,
            'errors' => [],
        ], JsonResponse::HTTP_BAD_REQUEST);
    }

    /** @return array<string,mixed> */
    private function serializeBilling(Billing $billing): array
    {
        return [
            'id' => $billing->getId(),
            'companyId' => $billing->getCompany()?->getId(),
            'label' => $billing->getLabel(),
            'amount' => $billing->getAmount(),
            'currency' => $billing->getCurrency(),
            'status' => $billing->getStatus(),
            'dueAt' => $billing->getDueAt()?->format(DATE_ATOM),
            'paidAt' => $billing->getPaidAt()?->format(DATE_ATOM),
        ];
    }

    /** @return array<string,mixed> */
    private function serializeTaskRequest(TaskRequest $taskRequest): array
    {
        return [
            'id' => $taskRequest->getId(),
            'taskId' => $taskRequest->getTask()?->getId(),
            'repositoryId' => $taskRequest->getRepository()?->getId(),
            'title' => $taskRequest->getTitle(),
            'description' => $taskRequest->getDescription(),
            'status' => $taskRequest->getStatus()->value,
            'requestedAt' => $taskRequest->getRequestedAt()->format(DATE_ATOM),
            'resolvedAt' => $taskRequest->getResolvedAt()?->format(DATE_ATOM),
        ];
    }
}
