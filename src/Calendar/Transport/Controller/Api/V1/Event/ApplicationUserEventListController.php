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
class ApplicationUserEventListController
{
    public function __construct(private readonly EventListService $eventListService)
    {
    }

    #[Route(path: '/v1/calendar/private/applications/{applicationSlug}/events', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));
        $filters = [
            'title' => trim((string) $request->query->get('title', '')),
            'description' => trim((string) $request->query->get('description', '')),
            'location' => trim((string) $request->query->get('location', '')),
        ];

        return new JsonResponse($this->eventListService->getByApplicationSlugAndUser($applicationSlug, $loggedInUser, $filters, $page, $limit));
    }
}
