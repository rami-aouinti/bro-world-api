<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\UserStory;

use App\User\Application\Service\UserStoryService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Stories')]
final readonly class GetActiveStoriesController
{
    public function __construct(
        private UserStoryService $userStoryService,
    ) {
    }

    #[Route('/v1/private/stories', methods: [Request::METHOD_GET])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function __invoke(User $loggedInUser, Request $request): JsonResponse
    {
        $limit = max(1, min(100, $request->query->getInt('limit', 50)));

        return new JsonResponse([
            'stories' => $this->userStoryService->getActiveStories($loggedInUser, $limit),
        ]);
    }
}
