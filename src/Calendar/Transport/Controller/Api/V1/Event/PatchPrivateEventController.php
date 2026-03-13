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
#[OA\Patch(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_patch', summary: 'Modifier un événement personnel', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'title', type: 'string', example: 'Entretien RH - décalé'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:30:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:30:00+00:00'), new OA\Property(property: 'visibility', type: 'string', enum: ['private', 'public']), new OA\Property(property: 'isAllDay', type: 'boolean'), new OA\Property(property: 'timezone', type: 'string', nullable: true), new OA\Property(property: 'location', type: 'string', nullable: true), new OA\Property(property: 'url', type: 'string', nullable: true), new OA\Property(property: 'color', type: 'string', nullable: true), new OA\Property(property: 'backgroundColor', type: 'string', nullable: true), new OA\Property(property: 'borderColor', type: 'string', nullable: true), new OA\Property(property: 'textColor', type: 'string', nullable: true), new OA\Property(property: 'organizerName', type: 'string', nullable: true), new OA\Property(property: 'organizerEmail', type: 'string', nullable: true), new OA\Property(property: 'attendees', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'rrule', type: 'string', nullable: true), new OA\Property(property: 'recurrenceExceptions', type: 'array', items: new OA\Items(type: 'string', format: 'date-time')), new OA\Property(property: 'recurrenceEndAt', type: 'string', format: 'date-time', nullable: true), new OA\Property(property: 'recurrenceCount', type: 'integer', nullable: true), new OA\Property(property: 'reminders', type: 'array', items: new OA\Items(type: 'object')), new OA\Property(property: 'metadata', type: 'object', additionalProperties: true)])), tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'UUID/payload invalide')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class PatchPrivateEventController
{
    public function __construct(
        public MessageServiceInterface   $messageService,
        public EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

    /**
     * @throws Throwable
     */
    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $eventId, Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivatePatchCommand($eventId, $request->toArray(), $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
