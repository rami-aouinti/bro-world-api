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
#[OA\Post(path: '/v1/calendar/private/events', operationId: 'calendar_private_event_create', summary: 'Créer un événement personnel', tags: ['Calendar Event'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'startAt', 'endAt'], properties: [new OA\Property(property: 'title', type: 'string', minLength: 1, maxLength: 255, example: 'Entretien RH'), new OA\Property(property: 'description', type: 'string', example: 'Point de cadrage'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:00:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:00:00+00:00'), new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'confirmed'), new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Paris'), new OA\Property(property: 'visibility', type: 'string', enum: ['private', 'public'], example: 'private'), new OA\Property(property: 'isAllDay', type: 'boolean', example: false), new OA\Property(property: 'timezone', type: 'string', nullable: true, example: 'Europe/Paris'), new OA\Property(property: 'url', type: 'string', nullable: true, example: 'https://example.com/event'), new OA\Property(property: 'color', type: 'string', nullable: true, example: '#2563eb'), new OA\Property(property: 'backgroundColor', type: 'string', nullable: true, example: '#dbeafe'), new OA\Property(property: 'borderColor', type: 'string', nullable: true, example: '#1d4ed8'), new OA\Property(property: 'textColor', type: 'string', nullable: true, example: '#0f172a'), new OA\Property(property: 'organizerName', type: 'string', nullable: true, example: 'Jane Doe'), new OA\Property(property: 'organizerEmail', type: 'string', nullable: true, example: 'jane@example.com'), new OA\Property(property: 'attendees', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'rrule', type: 'string', nullable: true, example: 'FREQ=WEEKLY;COUNT=4'), new OA\Property(property: 'recurrenceExceptions', type: 'array', items: new OA\Items(type: 'string', format: 'date-time')), new OA\Property(property: 'recurrenceEndAt', type: 'string', format: 'date-time', nullable: true, example: '2026-06-01T00:00:00+00:00'), new OA\Property(property: 'recurrenceCount', type: 'integer', nullable: true, example: 4), new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'metadata', type: 'object', additionalProperties: true)])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'Payload invalide'), new OA\Response(response: 422, description: 'Règle date invalide')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class CreatePrivateEventController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

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
