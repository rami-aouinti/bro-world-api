<?php

declare(strict_types=1);

namespace App\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Application\Service\CalendarApplicationAccessService;
use App\Calendar\Application\Service\EventListService;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Calendar Event')]
#[OA\Get(
    path: '/v1/calendar/events/owner',
    operationId: 'calendar_application_owner_event_list',
    summary: 'Lister tous les événements du calendar application (owner only)',
    tags: ['Calendar Event'],
    parameters: [
        new OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'crm-general-core')),
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
        new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Liste paginée owner'),
        new OA\Response(response: 403, description: 'Réservé au owner de l\'application'),
    ]
)]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ApplicationOwnerEventListController
{
    public function __construct(
        public readonly EventListService $eventListService,
        public readonly CalendarApplicationAccessService $calendarApplicationAccessService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[Route(path: '/v1/calendar/events/owner', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $this->calendarApplicationAccessService->requireOwner($applicationSlug, $loggedInUser);

        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'title' => trim((string)$request->query->get('title', '')),
            'description' => trim((string)$request->query->get('description', '')),
            'location' => trim((string)$request->query->get('location', '')),
        ];

        return new JsonResponse($this->eventListService->getByApplicationSlugForOwner($applicationSlug, $filters, $page, $limit));
    }
}
