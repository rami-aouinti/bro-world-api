<?php

declare(strict_types=1);

namespace App\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Service\EventMutationInputFactory;
use App\General\Domain\Service\Interfaces\MessageServiceInterface;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Calendar Event')]
#[OA\Post(path: '/v1/calendar/private/applications/{applicationSlug}/events', operationId: 'calendar_application_event_create', summary: 'Créer un événement application', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'bro-world'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'startAt', 'endAt'], properties: [new OA\Property(property: 'title', type: 'string', example: 'Demo client'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-05-12T13:00:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-05-12T14:00:00+00:00'), new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'tentative'), new OA\Property(property: 'visibility', type: 'string', enum: ['private', 'public'], example: 'private'), new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Paris'), new OA\Property(property: 'isAllDay', type: 'boolean', example: false), new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Europe/Paris'), new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://example.com/event'), new OA\Property(property: 'color', type: 'string', nullable: true, example: '#2563eb'), new OA\Property(property: 'backgroundColor', type: 'string', nullable: true, example: '#dbeafe'), new OA\Property(property: 'borderColor', type: 'string', nullable: true, example: '#1d4ed8'), new OA\Property(property: 'textColor', type: 'string', nullable: true, example: '#0f172a'), new OA\Property(property: 'organizerName', type: 'string', nullable: true, example: 'Jane Doe'), new OA\Property(property: 'organizerEmail', type: 'string', nullable: true, example: 'jane@example.com'), new OA\Property(property: 'attendees', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'rrule', type: 'string', nullable: true, example: 'FREQ=WEEKLY;COUNT=4'), new OA\Property(property: 'recurrenceExceptions', type: 'array', items: new OA\Items(type: 'string', format: 'date-time')), new OA\Property(property: 'recurrenceEndAt', type: 'string', format: 'date-time', nullable: true, example: '2026-06-01T00:00:00+00:00'), new OA\Property(property: 'recurrenceCount', type: 'integer', nullable: true, example: 4), new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'metadata', type: 'object', additionalProperties: true)])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 422, description: 'Dates invalides')])]
#[OA\Patch(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}', operationId: 'calendar_application_event_patch', summary: 'Modifier un événement application', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'bro-world')), new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'title', type: 'string', example: 'Demo client confirmée'), new OA\Property(property: 'visibility', type: 'string', enum: ['private', 'public']), new OA\Property(property: 'isAllDay', type: 'boolean'), new OA\Property(property: 'timezone', type: 'string', nullable: true), new OA\Property(property: 'location', type: 'string', nullable: true), new OA\Property(property: 'url', type: 'string', nullable: true), new OA\Property(property: 'color', type: 'string', nullable: true), new OA\Property(property: 'backgroundColor', type: 'string', nullable: true), new OA\Property(property: 'borderColor', type: 'string', nullable: true), new OA\Property(property: 'textColor', type: 'string', nullable: true), new OA\Property(property: 'organizerName', type: 'string', nullable: true), new OA\Property(property: 'organizerEmail', type: 'string', nullable: true), new OA\Property(property: 'attendees', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'rrule', type: 'string', nullable: true), new OA\Property(property: 'recurrenceExceptions', type: 'array', items: new OA\Items(type: 'string', format: 'date-time')), new OA\Property(property: 'recurrenceEndAt', type: 'string', format: 'date-time', nullable: true), new OA\Property(property: 'recurrenceCount', type: 'integer', nullable: true), new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'metadata', type: 'object', additionalProperties: true)])), responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
#[OA\Delete(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}', operationId: 'calendar_application_event_delete', summary: 'Supprimer un événement application', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'bro-world')), new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Suppression acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationOwnerEventMutationController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

    #[Route(path: '/v1/calendar/private/applications/{applicationSlug}/events', methods: [Request::METHOD_POST])]
    public function create(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createApplicationCreateCommand($request->toArray(), $loggedInUser->getId(), $applicationSlug);
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $applicationSlug, string $eventId, Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createApplicationPatchCommand($applicationSlug, $eventId, $request->toArray(), $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $applicationSlug, string $eventId, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createApplicationDeleteCommand($applicationSlug, $eventId, $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}/cancel', methods: [Request::METHOD_POST])]
    public function cancel(string $applicationSlug, string $eventId, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createApplicationCancelCommand($applicationSlug, $eventId, $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
