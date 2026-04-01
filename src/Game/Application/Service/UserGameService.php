<?php

declare(strict_types=1);

namespace App\Game\Application\Service;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameSession;
use App\Game\Domain\Entity\UserGame;
use App\Game\Domain\Enum\GameStatus;
use App\Game\Domain\Enum\UserGameLevel;
use App\Game\Domain\Enum\UserGameResult;
use App\Game\Domain\Enum\UserGameStatus;
use App\Game\Infrastructure\Repository\GameLevelCostRepository;
use App\Game\Infrastructure\Repository\UserGameRepository;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Throwable;

final readonly class UserGameService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private GameLevelCostRepository $gameLevelCostRepository,
        private UserGameRepository $userGameRepository,
    ) {
    }

    public function start(Game $game, User $user, UserGameLevel $level): GameSession
    {
        $costConfig = $this->gameLevelCostRepository->findOneByGameAndLevel($game, $level);
        if (null === $costConfig) {
            throw new UnprocessableEntityHttpException('No coins cost configured for the selected game and level.');
        }

        $entryCost = $costConfig->getMinCoinsCost();
        if ($user->getCoins() < $entryCost) {
            throw new UnprocessableEntityHttpException('Insufficient coins balance for this game level.');
        }

        $user->setCoins($user->getCoins() - $entryCost);

        return (new GameSession())
            ->setGame($game)
            ->setUser($user)
            ->setStatus(GameStatus::ACTIVE)
            ->setStartedAt(new DateTimeImmutable())
            ->setContext([
                'selectedLevel' => $level->value,
                'entryCostCoins' => $entryCost,
                'resultSubmitted' => false,
            ]);
    }

    /**
     * @param Game $game
     * @param User $user
     * @param UserGameLevel $level
     * @return array{session: GameSession, userGame: UserGame}
     * @throws Throwable
     */
    public function startTracked(Game $game, User $user, UserGameLevel $level): array
    {
        if ($game->getStatus() !== GameStatus::ACTIVE) {
            throw new UnprocessableEntityHttpException('Game is not available.');
        }

        return $this->entityManager->getConnection()->transactional(function () use ($game, $user, $level): array {
            $session = $this->start($game, $user, $level);
            $entryCost = (int)($session->getContext()['entryCostCoins'] ?? 0);

            $userGame = (new UserGame())
                ->setUser($user)
                ->setGame($game)
                ->setSession($session)
                ->setStatus(UserGameStatus::STARTED)
                ->setSelectedLevel($level)
                ->setEntryCostCoins($entryCost)
                ->setRewardOrPenaltyCoins(0);

            $this->entityManager->persist($user);
            $this->entityManager->persist($session);
            $this->entityManager->persist($userGame);
            $this->entityManager->flush();

            return [
                'session' => $session,
                'userGame' => $userGame,
            ];
        });
    }

    public function finishSession(GameSession $session, User $user, UserGameResult $result): UserGame
    {
        if ($session->getUser()?->getId() !== $user->getId()) {
            throw new ConflictHttpException('You do not have access to this game session.');
        }

        $userGame = $this->userGameRepository->findOneBySession($session);
        if (!$userGame instanceof UserGame) {
            throw new BadRequestHttpException('Game session is not linked to a user game trace.');
        }

        if ($userGame->getStatus() === UserGameStatus::FINISHED || $session->getStatus() === GameStatus::COMPLETED) {
            throw new ConflictHttpException('Game session is already finished.');
        }

        $game = $userGame->getGame();
        if (null === $game) {
            throw new BadRequestHttpException('User game trace has no related game.');
        }

        $costConfig = $this->gameLevelCostRepository->findOneByGameAndLevel($game, $userGame->getSelectedLevel());
        if (null === $costConfig) {
            throw new UnprocessableEntityHttpException('No coins configuration found for this game level.');
        }

        $delta = $result === UserGameResult::WIN
            ? abs($costConfig->getWinRewardCoins())
            : -abs($costConfig->getLosePenaltyCoins());

        return $this->entityManager->getConnection()->transactional(function () use ($session, $user, $userGame, $result, $delta): UserGame {
            $newBalance = $user->getCoins() + $delta;
            if ($newBalance < 0) {
                throw new UnprocessableEntityHttpException('Operation would result in a negative coins balance.');
            }

            $user->setCoins($newBalance);
            $session->setStatus(GameStatus::COMPLETED)->setEndedAt(new DateTimeImmutable());

            $context = $session->getContext();
            $context['resultSubmitted'] = true;
            $context['result'] = $result->value;
            $session->setContext($context);

            $userGame
                ->setStatus(UserGameStatus::FINISHED)
                ->setResult($result)
                ->setRewardOrPenaltyCoins($delta);

            $this->entityManager->persist($user);
            $this->entityManager->persist($session);
            $this->entityManager->persist($userGame);
            $this->entityManager->flush();

            return $userGame;
        });
    }

    public function submitResult(
        GameSession $session,
        User $user,
        UserGameResult $result,
        int $coinsAmount,
        string $idempotencyKey,
    ): UserGame {
        if ($idempotencyKey === '') {
            throw new BadRequestHttpException('idempotencyKey is required.');
        }

        $existing = $this->userGameRepository->findOneByUserAndIdempotencyKey($user, $idempotencyKey);
        if ($existing instanceof UserGame) {
            return $existing;
        }

        $context = $session->getContext();
        if (($context['resultSubmitted'] ?? false) === true) {
            throw new ConflictHttpException('Game result has already been submitted for this session.');
        }

        $levelRaw = (string)($context['selectedLevel'] ?? '');
        $entryCost = (int)($context['entryCostCoins'] ?? 0);

        $level = UserGameLevel::tryFrom($levelRaw);
        if (null === $level) {
            throw new BadRequestHttpException('Session has no valid selected level metadata.');
        }

        $delta = $result === UserGameResult::WIN ? abs($coinsAmount) : -abs($coinsAmount);

        return $this->entityManager->getConnection()->transactional(function () use ($session, $user, $result, $delta, $entryCost, $level, $idempotencyKey): UserGame {
            $newBalance = $user->getCoins() + $delta;
            if ($newBalance < 0) {
                throw new UnprocessableEntityHttpException('Operation would result in a negative coins balance.');
            }

            $user->setCoins($newBalance);

            $context = $session->getContext();
            $context['resultSubmitted'] = true;

            $session
                ->setStatus(GameStatus::COMPLETED)
                ->setEndedAt(new DateTimeImmutable())
                ->setContext($context);

            $game = $session->getGame();
            if (null === $game) {
                throw new BadRequestHttpException('Session has no related game.');
            }

            $userGame = (new UserGame())
                ->setUser($user)
                ->setGame($game)
                ->setSession($session)
                ->setStatus(UserGameStatus::FINISHED)
                ->setSelectedLevel($level)
                ->setEntryCostCoins($entryCost)
                ->setResult($result)
                ->setRewardOrPenaltyCoins($delta)
                ->setIdempotencyKey($idempotencyKey);

            $this->entityManager->persist($user);
            $this->entityManager->persist($session);
            $this->entityManager->persist($userGame);
            $this->entityManager->flush();

            return $userGame;
        });
    }
}
