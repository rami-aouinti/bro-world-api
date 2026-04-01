<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

use App\Game\Application\DTO\GameSessionResponseDto;
use App\Game\Application\Service\UserGameService;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Enum\UserGameLevel;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Game')]
final readonly class StartGameController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserGameService $userGameService,
    ) {
    }

    #[Route('/v1/games/{id}/plays/start', methods: [Request::METHOD_POST])]
    #[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
    #[OA\Post(summary: 'Start a user game play.', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'level', type: 'string', example: 'easy')], type: 'object')), responses: [new OA\Response(response: 201, description: 'Play started.'), new OA\Response(response: 404, description: 'Game not found.'), new OA\Response(response: 422, description: 'Validation failed.'), new OA\Response(response: 401, description: 'Authentication required.')])]
    public function __invoke(Game $game, Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $level = UserGameLevel::tryFrom(strtolower(trim((string)($payload['level'] ?? ''))));
        if (null === $level) {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => ['level is required and must be one of: easy, medium, hard.'],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $session = $this->userGameService->start($game, $loggedInUser, $level);
        } catch (HttpExceptionInterface $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], $exception->getStatusCode());
        }

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return new JsonResponse([
            'session' => GameSessionResponseDto::fromEntity($session)->toArray(),
            'coins' => $loggedInUser->getCoins(),
        ], JsonResponse::HTTP_CREATED);
    }

    /**
     * @return array<string,mixed>|JsonResponse
     */
    private function decodeRequest(Request $request): array|JsonResponse
    {
        try {
            return (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return new JsonResponse([
                'message' => 'Invalid JSON payload.',
            ], JsonResponse::HTTP_BAD_REQUEST);
        }
    }
}
