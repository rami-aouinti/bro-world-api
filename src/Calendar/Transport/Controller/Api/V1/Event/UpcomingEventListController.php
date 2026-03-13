<?php

declare(strict_types=1);

namespace App\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Service\EventListService;
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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class UpcomingEventListController
{
    public function __construct(
        public EventListService $eventListService
    ) {
    }

    #[Route(path: '/v1/calendar/events/upcoming', methods: [Request::METHOD_GET])]
    #[OA\Get(
        path: '/v1/calendar/events/upcoming',
        operationId: 'calendar_upcoming_event_list',
        summary: 'Lister mes 3 événements les plus proches (optionnellement par application)',
        tags: ['Calendar Event'],
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'query', required: false, schema: new OA\Schema(type: 'string', example: 'crm-pipeline-pro')),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 20, minimum: 1, example: 3)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des événements à venir'),
            new OA\Response(response: 401, description: 'Authentification requise'),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $applicationSlug = trim((string)$request->query->get('applicationSlug', ''));
        $limit = max(1, min(20, $request->query->getInt('limit', 3)));

        return new JsonResponse($this->eventListService->getUpcoming(
            $loggedInUser,
            $applicationSlug !== '' ? $applicationSlug : null,
            $limit,
        ));
    }
}
