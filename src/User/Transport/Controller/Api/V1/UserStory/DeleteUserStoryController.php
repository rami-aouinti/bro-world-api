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
final readonly class DeleteUserStoryController
{
    public function __construct(
        private UserStoryService $userStoryService,
    ) {
    }

    #[Route('/v1/private/stories/{id}', methods: [Request::METHOD_DELETE])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function __invoke(User $loggedInUser, string $id): JsonResponse
    {
        $this->userStoryService->deleteStory($loggedInUser, $id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
