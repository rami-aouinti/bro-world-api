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
use Throwable;

#[AsController]
#[OA\Tag(name: 'Calendar Event')]
#[OA\Post(
    path: '/v1/calendar/private/events',
    operationId: 'calendar_private_event_create',
    summary: 'Créer un événement personnel',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['title', 'startAt', 'endAt'],
            properties: [
                new OA\Property(property: 'title', type: 'string', maxLength: 255, minLength: 1, example: 'Entretien RH'),
                new OA\Property(property: 'description', type: 'string', example: 'Point de cadrage'),
                new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:00:00+00:00'),
                new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:00:00+00:00'),
                new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'confirmed'),
                new OA\Property(property: 'location', type: 'string', example: 'Paris', nullable: true),
                new OA\Property(property: 'visibility', type: 'string', enum: ['private', 'public'], example: 'private'),
                new OA\Property(property: 'isAllDay', type: 'boolean', example: false),
                new OA\Property(property: 'timezone', type: 'string', example: 'Europe/Paris', nullable: true),
                new OA\Property(property: 'url', type: 'string', example: 'https://example.com/event', nullable: true),
                new OA\Property(property: 'color', type: 'string', example: '#2563eb', nullable: true),
                new OA\Property(property: 'backgroundColor', type: 'string', example: '#dbeafe', nullable: true),
                new OA\Property(property: 'borderColor', type: 'string', example: '#1d4ed8', nullable: true),
                new OA\Property(property: 'textColor', type: 'string', example: '#0f172a', nullable: true),
                new OA\Property(property: 'organizerName', type: 'string', example: 'Jane Doe', nullable: true),
                new OA\Property(property: 'organizerEmail', type: 'string', example: 'jane@example.com', nullable: true),
                new OA\Property(property: 'attendees', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(property: 'rrule', type: 'string', example: 'FREQ=WEEKLY;COUNT=4', nullable: true),
                new OA\Property(property: 'recurrenceExceptions', type: 'array', items: new OA\Items(type: 'string', format: 'date-time')),
                new OA\Property(property: 'recurrenceEndAt', type: 'string', format: 'date-time', example: '2026-06-01T00:00:00+00:00', nullable: true),
                new OA\Property(property: 'recurrenceCount', type: 'integer', example: 4, nullable: true),
                new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(type: 'object')),
                new OA\Property(
                    property: 'metadata',
                    type: 'object',
                    additionalProperties: true)]
        )),
    tags: ['Calendar Event'],
    responses: [new OA\Response(
        response: 202, description: 'Commande acceptée'),
        new OA\Response(response: 400, description: 'Payload invalide'),
        new OA\Response(response: 422, description: 'Règle date invalide')]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class CreatePrivateEventController
{
    public function __construct(
        public MessageServiceInterface   $messageService,
        public EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(path: '/v1/calendar/private/events', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivateCreateCommand($request->toArray(), $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
