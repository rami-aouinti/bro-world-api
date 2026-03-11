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
#[OA\Post(path: '/v1/calendar/private/events', operationId: 'calendar_private_event_create', summary: 'Créer un événement personnel', tags: ['Calendar Event'], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'startAt', 'endAt'], properties: [new OA\Property(property: 'title', type: 'string', minLength: 1, maxLength: 255, example: 'Entretien RH'), new OA\Property(property: 'description', type: 'string', example: 'Point de cadrage'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:00:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:00:00+00:00'), new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'confirmed'), new OA\Property(property: 'location', type: 'string', nullable: true, example: 'Paris')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'Payload invalide'), new OA\Response(response: 422, description: 'Règle date invalide')])]
#[OA\Patch(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_patch', summary: 'Modifier un événement personnel', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'title', type: 'string', example: 'Entretien RH - décalé'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-04-10T09:30:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-04-10T10:30:00+00:00')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 400, description: 'UUID/payload invalide')])]
#[OA\Delete(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_delete', summary: 'Supprimer un événement personnel', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Suppression acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class UserEventMutationController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

    #[Route(path: '/v1/calendar/private/events', methods: [Request::METHOD_POST])]
    public function create(Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivateCreateCommand($request->toArray(), $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_PATCH])]
    public function patch(string $eventId, Request $request, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivatePatchCommand($eventId, $request->toArray(), $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_DELETE])]
    public function delete(string $eventId, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivateDeleteCommand($eventId, $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}/cancel', methods: [Request::METHOD_POST])]
    public function cancel(string $eventId, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivateCancelCommand($eventId, $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
