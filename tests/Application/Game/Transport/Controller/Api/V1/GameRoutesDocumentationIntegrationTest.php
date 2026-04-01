<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class GameRoutesDocumentationIntegrationTest extends WebTestCase
{
    private const string GAME_UUID = '21000000-0000-1000-8000-000000000101';

    #[TestDox('Documented public game routes return expected minimal JSON structure.')]
    public function testDocumentedPublicGameRoutesReturnExpectedStructures(): void
    {
        $client = $this->getTestClient();

        $client->request('GET', self::API_URL_PREFIX . '/v1/public/game-catalog');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $catalog = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($catalog);
        self::assertArrayHasKey('subCategories', $catalog[0]);

        $client->request('GET', self::API_URL_PREFIX . '/v1/game-levels');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $levels = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('items', $levels);
        self::assertIsArray($levels['items']);
    }

    #[TestDox('Documented authenticated game routes return expected minimal JSON structure.')]
    public function testDocumentedAuthenticatedGameRoutesReturnExpectedStructures(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request('GET', self::API_URL_PREFIX . '/v1/games');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $games = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('items', $games);

        $client->request('GET', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $game = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('id', $game);

        $client->request('GET', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/leaderboard');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $leaderboard = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('items', $leaderboard);

        $client->request('GET', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/statistics');
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $statistics = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('items', $statistics);

        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/start', [], [], [], JSON::encode(['level' => 'easy']));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $started = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('session', $started);
        self::assertArrayHasKey('userGameId', $started);
        self::assertArrayHasKey('coins', $started);

        $sessionId = (string) $started['session']['id'];
        $client->request('POST', self::API_URL_PREFIX . '/v1/games/' . self::GAME_UUID . '/sessions/' . $sessionId . '/finish', [], [], [], JSON::encode(['result' => 'win']));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $finished = JSON::decode((string) $client->getResponse()->getContent(), true);
        self::assertArrayHasKey('userGame', $finished);
        self::assertArrayHasKey('coins', $finished);
    }
}
