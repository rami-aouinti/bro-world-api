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
#[OA\Delete(path: '/v1/calendar/private/events/{eventId}', operationId: 'calendar_private_event_delete', summary: 'Supprimer un événement personnel', tags: ['Calendar Event'], parameters: [new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'))], responses: [new OA\Response(response: 202, description: 'Suppression acceptée')])]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class DeletePrivateEventController
{
    public function __construct(
        private readonly MessageServiceInterface $messageService,
        private readonly EventMutationInputFactory $eventMutationInputFactory,
    ) {
    }

    #[Route(path: '/v1/calendar/private/events/{eventId}', methods: [Request::METHOD_DELETE])]
    public function __invoke(string $eventId, User $loggedInUser): JsonResponse
    {
        $command = $this->eventMutationInputFactory->createPrivateDeleteCommand($eventId, $loggedInUser->getId());
        $this->messageService->sendMessage($command);

        return new JsonResponse([
            'operationId' => $command->operationId,
            'id' => $eventId,
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
