<?php

declare(strict_types=1);

namespace App\User\Transport\Controller\Api\V1\UserStory;

use App\User\Application\Service\UserStoryService;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function trim;

#[AsController]
#[OA\Tag(name: 'Stories')]
final readonly class CreateUserStoryController
{
    public function __construct(
        private UserStoryService $userStoryService,
    ) {
    }

    #[Route('/v1/private/stories', methods: [Request::METHOD_POST])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    public function __invoke(User $loggedInUser, Request $request): JsonResponse
    {
        $payload = $this->extractPayload($request);
        $story = $this->userStoryService->createStory($loggedInUser, trim((string)($payload['imageUrl'] ?? '')));

        return new JsonResponse($story, JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractPayload(Request $request): array
    {
        try {
            $payload = $request->toArray();
        } catch (JsonException) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid JSON payload.');
        }

        if (!is_array($payload)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Payload must be a JSON object.');
        }

        return $payload;
    }
}
