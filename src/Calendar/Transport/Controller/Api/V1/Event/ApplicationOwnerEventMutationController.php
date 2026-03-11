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
#[OA\Post(path: '/v1/calendar/private/applications/{applicationSlug}/events', operationId: 'calendar_application_event_create', summary: 'Créer un événement application', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'bro-world'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['title', 'startAt', 'endAt'], properties: [new OA\Property(property: 'title', type: 'string', example: 'Demo client'), new OA\Property(property: 'startAt', type: 'string', format: 'date-time', example: '2026-05-12T13:00:00+00:00'), new OA\Property(property: 'endAt', type: 'string', format: 'date-time', example: '2026-05-12T14:00:00+00:00'), new OA\Property(property: 'status', type: 'string', enum: ['confirmed', 'tentative', 'cancelled'], example: 'tentative')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée'), new OA\Response(response: 422, description: 'Dates invalides')])]
#[OA\Patch(path: '/v1/calendar/private/applications/{applicationSlug}/events/{eventId}', operationId: 'calendar_application_event_patch', summary: 'Modifier un événement application', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string', example: 'bro-world')), new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'title', type: 'string', example: 'Demo client confirmée')])), responses: [new OA\Response(response: 202, description: 'Commande acceptée')])]
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
