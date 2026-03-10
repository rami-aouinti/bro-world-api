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
class UserEventListController
{
    public function __construct(
        private readonly EventListService $eventListService
    ) {
    }

    #[Route(path: '/v1/calendar/private/events', methods: [Request::METHOD_GET])]
    #[OA\Get(
        path: '/v1/calendar/private/events',
        operationId: 'calendar_private_event_list',
        summary: 'Lister mes événements calendrier',
        tags: ['Calendar Event'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page (min 1)', schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Taille de page (1..100)', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 20)),
            new OA\Parameter(name: 'title', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255, example: 'Entretien technique')),
            new OA\Parameter(name: 'description', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 1000, example: 'Préparation avec l’équipe produit')),
            new OA\Parameter(name: 'location', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 255, example: 'Visio Google Meet')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste paginée des événements'),
            new OA\Response(response: 401, description: 'Authentification requise'),
            new OA\Response(response: 500, description: 'Erreur interne serveur'),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'title' => trim((string)$request->query->get('title', '')),
            'description' => trim((string)$request->query->get('description', '')),
            'location' => trim((string)$request->query->get('location', '')),
        ];

        return new JsonResponse($this->eventListService->getByUser($loggedInUser, $filters, $page, $limit));
    }
}
