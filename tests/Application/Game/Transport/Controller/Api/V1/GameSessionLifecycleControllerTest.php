<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameLevelCost;
use App\Game\Domain\Enum\UserGameLevel;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class GameSessionLifecycleControllerTest extends WebTestCase
{
    private const string GAME_UUID = '21000000-0000-1000-8000-000000000101';

    #[TestDox('Start session returns 4xx when balance is insufficient for entry cost.')]
    public function testStartSessionFailsWhenCoinsAreInsufficient(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getUser($entityManager);
        $game = $this->getGame($entityManager);

        $this->upsertCostConfig($entityManager, $game, UserGameLevel::EASY, 400, 200, 200);
        $user->setCoins(300);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            'POST',
            self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/start',
            [],
            [],
            [],
            JSON::encode(['level' => 'easy']),
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Finishing with win updates coins using configured win reward.')]
    public function testFinishWinUpdatesCoins(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getUser($entityManager);
        $game = $this->getGame($entityManager);

        $this->upsertCostConfig($entityManager, $game, UserGameLevel::EASY, 400, 200, 200);
        $user->setCoins(1000);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/start', [], [], [], JSON::encode(['level' => 'easy']));

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $payload = JSON::decode((string)$client->getResponse()->getContent(), true);
        $sessionId = (string)$payload['session']['id'];

        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/' . $sessionId . '/finish', [], [], [], JSON::encode(['result' => 'win']));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $finishPayload = JSON::decode((string)$client->getResponse()->getContent(), true);
        self::assertSame(800, $finishPayload['coins']);
    }

    #[TestDox('Finishing with lose updates coins using configured lose penalty.')]
    public function testFinishLoseUpdatesCoins(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getUser($entityManager);
        $game = $this->getGame($entityManager);

        $this->upsertCostConfig($entityManager, $game, UserGameLevel::EASY, 400, 200, 200);
        $user->setCoins(1000);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/start', [], [], [], JSON::encode(['level' => 'easy']));
        $payload = JSON::decode((string)$client->getResponse()->getContent(), true);
        $sessionId = (string)$payload['session']['id'];

        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/' . $sessionId . '/finish', [], [], [], JSON::encode(['result' => 'lose']));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $finishPayload = JSON::decode((string)$client->getResponse()->getContent(), true);
        self::assertSame(400, $finishPayload['coins']);
    }

    #[TestDox('Double finish on same session is rejected.')]
    public function testDoubleFinishIsRejected(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $user = $this->getUser($entityManager);
        $game = $this->getGame($entityManager);

        $this->upsertCostConfig($entityManager, $game, UserGameLevel::EASY, 400, 200, 200);
        $user->setCoins(1000);
        $entityManager->persist($user);
        $entityManager->flush();

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/start', [], [], [], JSON::encode(['level' => 'easy']));
        $payload = JSON::decode((string)$client->getResponse()->getContent(), true);
        $sessionId = (string)$payload['session']['id'];

        $finishUrl = self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/' . $sessionId . '/finish';
        $client->request('POST', $finishUrl, [], [], [], JSON::encode(['result' => 'win']));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('POST', $finishUrl, [], [], [], JSON::encode(['result' => 'lose']));
        self::assertSame(Response::HTTP_CONFLICT, $client->getResponse()->getStatusCode());
    }

    private function getUser(EntityManagerInterface $entityManager): User
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => 'john-user']);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }

    private function getGame(EntityManagerInterface $entityManager): Game
    {
        $game = $entityManager->getRepository(Game::class)->find(self::GAME_UUID);
        self::assertInstanceOf(Game::class, $game);

        return $game;
    }

    private function upsertCostConfig(
        EntityManagerInterface $entityManager,
        Game $game,
        UserGameLevel $level,
        int $entryCost,
        int $winReward,
        int $losePenalty,
    ): void {
        $cost = $entityManager->getRepository(GameLevelCost::class)->findOneBy(['game' => $game, 'levelKey' => $level]);
        if (!$cost instanceof GameLevelCost) {
            $cost = (new GameLevelCost())->setGame($game)->setLevelKey($level);
        }

        $cost
            ->setMinCoinsCost($entryCost)
            ->setWinRewardCoins($winReward)
            ->setLosePenaltyCoins($losePenalty);

        $entityManager->persist($cost);
        $entityManager->flush();
    }
}
