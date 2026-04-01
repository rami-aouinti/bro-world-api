<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class PublicGameCatalogFixtureIntegrationTest extends WebTestCase
{
    #[TestDox('Public game catalog returns HTTP 200 and the exact JSON payload from fixtures.')]
    public function testPublicGameCatalogStrictJsonEqualsFixturePayload(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/public/game-catalog');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertIsArray($payload);

        $expectedPayload = [
            [
                'id' => 'cards',
                'nameKey' => 'cards',
                'descriptionKey' => 'cards',
                'img' => null,
                'icon' => null,
                'subCategories' => [
                    [
                        'id' => 'classic-cards',
                        'nameKey' => 'classic-cards',
                        'descriptionKey' => 'classic-cards',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'solitaire-classic',
                                'nameKey' => 'solitaire-classic',
                                'descriptionKey' => 'solitaire-classic',
                                'img' => null,
                                'icon' => null,
                                'component' => null,
                                'supportedModes' => ['solo'],
                                'categoryKey' => 'cards',
                                'subcategoryKey' => 'classic-cards',
                                'difficultyKey' => 'beginner',
                                'tags' => ['cards', 'solo'],
                                'features' => ['timer'],
                            ],
                            [
                                'id' => 'blackjack-classic',
                                'nameKey' => 'blackjack-classic',
                                'descriptionKey' => 'blackjack-classic',
                                'img' => null,
                                'icon' => null,
                                'component' => 'BlackjackClassic',
                                'supportedModes' => ['solo', 'versus'],
                                'categoryKey' => 'cards',
                                'subcategoryKey' => 'classic-cards',
                                'difficultyKey' => 'intermediate',
                                'tags' => ['cards', 'casino'],
                                'features' => ['score-multiplier'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'quick-cards',
                        'nameKey' => 'quick-cards',
                        'descriptionKey' => 'quick-cards',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'speed-duel-cards',
                                'nameKey' => 'speed-duel-cards',
                                'descriptionKey' => 'speed-duel-cards',
                                'img' => null,
                                'icon' => null,
                                'component' => null,
                                'supportedModes' => [],
                                'categoryKey' => 'cards',
                                'subcategoryKey' => 'quick-cards',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'board',
                'nameKey' => 'board',
                'descriptionKey' => 'board',
                'img' => null,
                'icon' => null,
                'subCategories' => [
                    [
                        'id' => 'table-classic',
                        'nameKey' => 'table-classic',
                        'descriptionKey' => 'table-classic',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'checkers-table',
                                'nameKey' => 'checkers-table',
                                'descriptionKey' => 'checkers-table',
                                'img' => null,
                                'icon' => null,
                                'component' => 'CheckersTable',
                                'supportedModes' => ['versus'],
                                'categoryKey' => 'board',
                                'subcategoryKey' => 'table-classic',
                                'difficultyKey' => 'beginner',
                                'tags' => ['board'],
                                'features' => ['ranked'],
                            ],
                            [
                                'id' => 'chess-table',
                                'nameKey' => 'chess-table',
                                'descriptionKey' => 'chess-table',
                                'img' => null,
                                'icon' => null,
                                'component' => 'ChessTable',
                                'supportedModes' => ['versus', 'online'],
                                'categoryKey' => 'board',
                                'subcategoryKey' => 'table-classic',
                                'difficultyKey' => 'advanced',
                                'tags' => ['board', 'strategy'],
                                'features' => ['elo', 'analysis'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'strategy-board',
                        'nameKey' => 'strategy-board',
                        'descriptionKey' => 'strategy-board',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'hexa-tactics',
                                'nameKey' => 'hexa-tactics',
                                'descriptionKey' => 'hexa-tactics',
                                'img' => null,
                                'icon' => null,
                                'component' => null,
                                'supportedModes' => ['solo', 'versus'],
                                'categoryKey' => 'board',
                                'subcategoryKey' => 'strategy-board',
                                'difficultyKey' => 'advanced',
                                'tags' => ['board', 'tactics'],
                                'features' => ['campaign'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'smart-games',
                'nameKey' => 'smart-games',
                'descriptionKey' => 'smart-games',
                'img' => null,
                'icon' => null,
                'subCategories' => [
                    [
                        'id' => 'words-language',
                        'nameKey' => 'words-language',
                        'descriptionKey' => 'words-language',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'word-link',
                                'nameKey' => 'word-link',
                                'descriptionKey' => 'word-link',
                                'img' => null,
                                'icon' => null,
                                'component' => 'WordLink',
                                'supportedModes' => ['solo'],
                                'categoryKey' => 'smart-games',
                                'subcategoryKey' => 'words-language',
                                'difficultyKey' => 'beginner',
                                'tags' => ['words'],
                                'features' => ['dictionary'],
                            ],
                            [
                                'id' => 'anagram-rush',
                                'nameKey' => 'anagram-rush',
                                'descriptionKey' => 'anagram-rush',
                                'img' => null,
                                'icon' => null,
                                'component' => null,
                                'supportedModes' => [],
                                'categoryKey' => 'smart-games',
                                'subcategoryKey' => 'words-language',
                                'difficultyKey' => 'intermediate',
                                'tags' => ['words', 'speed'],
                                'features' => ['daily-challenge'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'logic-brain',
                        'nameKey' => 'logic-brain',
                        'descriptionKey' => 'logic-brain',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'number-grid',
                                'nameKey' => 'number-grid',
                                'descriptionKey' => 'number-grid',
                                'img' => null,
                                'icon' => null,
                                'component' => 'NumberGrid',
                                'supportedModes' => ['solo'],
                                'categoryKey' => 'smart-games',
                                'subcategoryKey' => 'logic-brain',
                                'difficultyKey' => 'advanced',
                                'tags' => ['logic', 'numbers'],
                                'features' => ['hints'],
                            ],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'arcade',
                'nameKey' => 'arcade',
                'descriptionKey' => 'arcade',
                'img' => null,
                'icon' => null,
                'subCategories' => [
                    [
                        'id' => 'reflex-arcade',
                        'nameKey' => 'reflex-arcade',
                        'descriptionKey' => 'reflex-arcade',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'color-reactor',
                                'nameKey' => 'color-reactor',
                                'descriptionKey' => 'color-reactor',
                                'img' => null,
                                'icon' => null,
                                'component' => null,
                                'supportedModes' => ['solo'],
                                'categoryKey' => 'arcade',
                                'subcategoryKey' => 'reflex-arcade',
                                'difficultyKey' => 'intermediate',
                                'tags' => ['reflex'],
                                'features' => ['combo'],
                            ],
                        ],
                    ],
                    [
                        'id' => 'runner-arcade',
                        'nameKey' => 'runner-arcade',
                        'descriptionKey' => 'runner-arcade',
                        'img' => null,
                        'icon' => null,
                        'games' => [
                            [
                                'id' => 'sky-run',
                                'nameKey' => 'sky-run',
                                'descriptionKey' => 'sky-run',
                                'img' => null,
                                'icon' => null,
                                'component' => 'SkyRun',
                                'supportedModes' => ['solo', 'endless'],
                                'categoryKey' => 'arcade',
                                'subcategoryKey' => 'runner-arcade',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        self::assertSame($expectedPayload, $payload);

        // Sensitive cases
        self::assertNull($payload[0]['subCategories'][0]['games'][0]['component']);
        self::assertSame([], $payload[0]['subCategories'][1]['games'][0]['supportedModes']);
        self::assertArrayNotHasKey('tags', $payload[0]['subCategories'][1]['games'][0]);
        self::assertArrayNotHasKey('features', $payload[0]['subCategories'][1]['games'][0]);
        self::assertArrayHasKey('tags', $payload[0]['subCategories'][0]['games'][0]);
        self::assertArrayHasKey('features', $payload[0]['subCategories'][0]['games'][0]);
    }

    #[TestDox('Public game catalog endpoint is accessible without authentication.')]
    public function testPublicGameCatalogIsPublicWithoutAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/public/game-catalog');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
    }
}
