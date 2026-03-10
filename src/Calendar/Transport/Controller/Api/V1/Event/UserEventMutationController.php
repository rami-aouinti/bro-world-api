<?php

declare(strict_types=1);

namespace App\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Message\CancelEventCommand;
use App\Calendar\Application\Message\CreateEventCommand;
use App\Calendar\Application\Message\DeleteEventCommand;
use App\Calendar\Application\Message\PatchEventCommand;
use App\Calendar\Domain\Enum\EventStatus;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\General\Transport\Http\ValidationErrorFactory;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[AsController]
#[OA\Tag(name: 'Calendar Event')]
#[OA\Post(path: '/v1/calendar/private/events', operationId: 'calendar_private_event_create', summary: 'Créer un événement personnel', tags: ['Calendar Event'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'startAt', 'endAt'], properties: [new OA\Property(property: 'title', type: 'string', minLength: 1, maxLength: 255, example: 'Entretien RH'), new OA\Property(property: 'description', type: 'string', example: 'Point de cadrage'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:00:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:00:00+00:00'), new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'confirmed'), new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Paris')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'Payload invalide'), new OA\Response(response: 422, description: 'Règle date invalide')])]
#[OA\Patch(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_patch', summary: 'Modifier un événement personnel', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'title', type: 'string', example: 'Entretien RH - décalé'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:30:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:30:00+00:00')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'UUID/payload invalide')])]
#[OA\Delete(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_delete', summary: 'Supprimer un événement personnel', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Suppression acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserEventMutationController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService
    ) {
    }

    #[Route(path: '/v1/calendar/private/events', methods: [Request::METHOD_POST])]
    public function create(Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $request->toArray();
        $startAt = $this->requireDate($payload, 'startAt');
        $endAt = $this->requireDate($payload, 'endAt');
        $this->assertDateRange($startAt, $endAt);

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new CreateEventCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            title: $this->requireString($payload, 'title'),
            description: (string)($payload['description'] ?? ''),
            startAt: $startAt,
            endAt: $endAt,
            status: $this->parseStatus((string)($payload['status'] ?? EventStatus::CONFIRMED->value)),
            location: isset($payload['location']) && is_string($payload['location']) ? $payload['location'] : null,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $eventId, Request $request, User $loggedInUser): JsonResponse
    {
        $this->assertUuid($eventId, 'eventId');
        $payload = $request->toArray();

        $startAt = isset($payload['startAt']) && is_string($payload['startAt']) ? $this->parseDate($payload['startAt'], 'startAt') : null;
        $endAt = isset($payload['endAt']) && is_string($payload['endAt']) ? $this->parseDate($payload['endAt'], 'endAt') : null;

        if ($startAt !== null && $endAt !== null) {
            $this->assertDateRange($startAt, $endAt);
        }

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new PatchEventCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            eventId: $eventId,
            title: isset($payload['title']) && is_string($payload['title']) ? $payload['title'] : null,
            description: isset($payload['description']) && is_string($payload['description']) ? $payload['description'] : null,
            startAt: $startAt,
            endAt: $endAt,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $eventId, User $loggedInUser): JsonResponse
    {
        $this->assertUuid($eventId, 'eventId');

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new DeleteEventCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            eventId: $eventId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}/cancel', methods: [Request::METHOD_POST])]
    public function cancel(string $eventId, User $loggedInUser): JsonResponse
    {
        $this->assertUuid($eventId, 'eventId');

        $operationId = \Ramsey\Uuid\Uuid::uuid4()->toString();
        $this->messageService->sendMessage(new CancelEventCommand(
            operationId: $operationId,
            actorUserId: $loggedInUser->getId(),
            eventId: $eventId,
        ));

        return new JsonResponse([
            'operationId' => $operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
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
