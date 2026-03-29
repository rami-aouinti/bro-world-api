<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

use App\Game\Application\DTO\CreateGameRequestDto;
use App\Game\Application\DTO\GameResponseDto;
use App\Game\Application\DTO\GameSessionResponseDto;
use App\Game\Application\DTO\GameStatisticResponseDto;
use App\Game\Application\DTO\LeaderboardEntryResponseDto;
use App\Game\Application\DTO\ManageGameSessionRequestDto;
use App\Game\Application\Service\GameSessionService;
use App\Game\Application\Service\GameStatisticService;
use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameScore;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Entity\GameStatistic;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Game')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GameController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameSessionService $gameSessionService,
        private GameStatisticService $gameStatisticService,
    ) {
    }

    #[Route('/v1/games', methods: [Request::METHOD_GET])]
    public function listGames(): JsonResponse
    {
        $games = $this->entityManager->getRepository(Game::class)->findBy([], ['createdAt' => 'DESC']);

        return new JsonResponse([
            'items' => array_map(
                static fn (Game $game): array => GameResponseDto::fromEntity($game)->toArray(),
                $games,
            ),
        ]);
    }

    #[Route('/v1/games', methods: [Request::METHOD_POST])]
    public function createGame(Request $request): JsonResponse
    {
        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = CreateGameRequestDto::fromArray($payload);

        if ($input->name === '' || $input->categoryId === '') {
            return new JsonResponse([
                'message' => 'Validation failed.',
                'errors' => [
                    'name and categoryId are required.',
                ],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $category = $this->entityManager->getRepository(GameCategory::class)->find($input->categoryId);
        if (!$category instanceof GameCategory) {
            return new JsonResponse([
                'message' => 'Game category not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $game = (new Game())
            ->setName($input->name)
            ->setCategory($category)
            ->setLevel($input->level)
            ->setStatus($input->status)
            ->setMetadata($input->metadata);

        $this->entityManager->persist($game);
        $this->entityManager->flush();

        return new JsonResponse(GameResponseDto::fromEntity($game)->toArray(), JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/games/{id}', methods: [Request::METHOD_GET])]
    public function gameDetail(string $id): JsonResponse
    {
        $game = $this->entityManager->getRepository(Game::class)->find($id);
        if (!$game instanceof Game) {
            return new JsonResponse(['message' => 'Game not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse(GameResponseDto::fromEntity($game)->toArray());
    }

    #[Route('/v1/games/{id}/sessions', methods: [Request::METHOD_POST])]
    public function manageSession(string $id, Request $request, User $loggedInUser): JsonResponse
    {
        $game = $this->entityManager->getRepository(Game::class)->find($id);
        if (!$game instanceof Game) {
            return new JsonResponse(['message' => 'Game not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = $this->decodeRequest($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = ManageGameSessionRequestDto::fromArray($payload);

        if ($input->isStart()) {
            $session = $this->gameSessionService->start($game, $loggedInUser, $input->context);
            $this->entityManager->persist($session);
            $this->entityManager->flush();

            return new JsonResponse(GameSessionResponseDto::fromEntity($session)->toArray(), JsonResponse::HTTP_CREATED);
        }

        if ($input->isComplete()) {
            if ($input->sessionId === null || $input->sessionId === '') {
                return new JsonResponse([
                    'message' => 'Validation failed.',
                    'errors' => ['sessionId is required when action is complete.'],
                ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
            }

            $session = $this->entityManager->getRepository(GameSession::class)->find($input->sessionId);
            if (!$session instanceof GameSession || $session->getGame()?->getId() !== $game->getId()) {
                return new JsonResponse(['message' => 'Game session not found.'], JsonResponse::HTTP_NOT_FOUND);
            }

            $score = $this->gameSessionService->complete($session, $input->context);
            $this->entityManager->persist($session);
            $this->entityManager->persist($score);
            $this->entityManager->flush();

            return new JsonResponse(GameSessionResponseDto::fromEntity($session, $score)->toArray());
        }

        return new JsonResponse([
            'message' => 'Validation failed.',
            'errors' => ['action must be one of: start, complete.'],
        ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
    }

    #[Route('/v1/games/{id}/leaderboard', methods: [Request::METHOD_GET])]
    public function leaderboard(string $id, Request $request): JsonResponse
    {
        $game = $this->entityManager->getRepository(Game::class)->find($id);
        if (!$game instanceof Game) {
            return new JsonResponse(['message' => 'Game not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $limit = max(1, min((int)$request->query->get('limit', 10), 100));

        $scores = $this->entityManager
            ->getRepository(GameScore::class)
            ->createQueryBuilder('score')
            ->innerJoin('score.session', 'session')
            ->andWhere('session.game = :game')
            ->setParameter('game', $game)
            ->orderBy('score.value', 'DESC')
            ->addOrderBy('score.calculatedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'items' => array_map(
                static fn (GameScore $score): array => LeaderboardEntryResponseDto::fromEntity($score)->toArray(),
                $scores,
            ),
        ]);
    }

    #[Route('/v1/games/{id}/statistics', methods: [Request::METHOD_GET])]
    public function statistics(string $id): JsonResponse
    {
        $game = $this->entityManager->getRepository(Game::class)->find($id);
        if (!$game instanceof Game) {
            return new JsonResponse(['message' => 'Game not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $sessions = $this->entityManager->getRepository(GameSession::class)->findBy(['game' => $game], ['startedAt' => 'ASC']);
        $scores = $this->entityManager
            ->getRepository(GameScore::class)
            ->createQueryBuilder('score')
            ->innerJoin('score.session', 'session')
            ->andWhere('session.game = :game')
            ->setParameter('game', $game)
            ->getQuery()
            ->getResult();

        $stats = $this->gameStatisticService->buildForGame($game, $sessions, $scores);

        return new JsonResponse([
            'items' => array_map(
                static fn (GameStatistic $stat): array => GameStatisticResponseDto::fromEntity($stat)->toArray(),
                $stats,
            ),
        ]);
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
