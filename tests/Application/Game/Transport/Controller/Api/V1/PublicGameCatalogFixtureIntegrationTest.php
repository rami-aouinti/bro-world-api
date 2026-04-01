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
                'img' => '/img/game/card-game.png',
                'icon' => 'mdi-cards-playing-outline',
                'subCategories' => [
                    [
                        'id' => 'classic-cards',
                        'nameKey' => 'classic-cards',
                        'descriptionKey' => 'classic-cards',
                        'img' => '/img/game/classic-cards.png',
                        'icon' => 'mdi-cards-outline',
                        'games' => [
                            [
                                'id' => 'solitaire-classic',
                                'nameKey' => 'solitaire-classic',
                                'descriptionKey' => 'solitaire-classic',
                                'img' => '/img/game/solitaire-classic.png',
                                'icon' => 'mdi-cards-playing-outline',
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
                                'img' => '/img/game/blackjack-classic.png',
                                'icon' => 'mdi-cards-playing-spade-outline',
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
                        'img' => '/img/game/quick-cards.png',
                        'icon' => 'mdi-lightning-bolt-outline',
                        'games' => [
                            [
                                'id' => 'speed-duel-cards',
                                'nameKey' => 'speed-duel-cards',
                                'descriptionKey' => 'speed-duel-cards',
                                'img' => '/img/game/speed-duel-cards.png',
                                'icon' => 'mdi-lightning-bolt-outline',
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
                'img' => '/img/game/board-game.png',
                'icon' => 'mdi-checkerboard',
                'subCategories' => [
                    [
                        'id' => 'table-classic',
                        'nameKey' => 'table-classic',
                        'descriptionKey' => 'table-classic',
                        'img' => '/img/game/table-classic.png',
                        'icon' => 'mdi-checkerboard',
                        'games' => [
                            [
                                'id' => 'checkers-table',
                                'nameKey' => 'checkers-table',
                                'descriptionKey' => 'checkers-table',
                                'img' => '/img/game/checkers-table.png',
                                'icon' => 'mdi-checkerboard',
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
                                'img' => '/img/game/chess-table.png',
                                'icon' => 'mdi-chess-king',
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
                        'img' => '/img/game/strategy-board.png',
                        'icon' => 'mdi-chess-queen',
                        'games' => [
                            [
                                'id' => 'hexa-tactics',
                                'nameKey' => 'hexa-tactics',
                                'descriptionKey' => 'hexa-tactics',
                                'img' => '/img/game/hexa-tactics.png',
                                'icon' => 'mdi-hexagon-multiple-outline',
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
                'img' => '/img/game/smart-game.png',
                'icon' => 'mdi-brain',
                'subCategories' => [
                    [
                        'id' => 'words-language',
                        'nameKey' => 'words-language',
                        'descriptionKey' => 'words-language',
                        'img' => '/img/game/words-language.png',
                        'icon' => 'mdi-alphabetical-variant',
                        'games' => [
                            [
                                'id' => 'word-link',
                                'nameKey' => 'word-link',
                                'descriptionKey' => 'word-link',
                                'img' => '/img/game/word-link.png',
                                'icon' => 'mdi-alphabetical-variant',
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
                                'img' => '/img/game/anagram-rush.png',
                                'icon' => 'mdi-format-letter-case',
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
                        'img' => '/img/game/logic-brain.png',
                        'icon' => 'mdi-brain',
                        'games' => [
                            [
                                'id' => 'number-grid',
                                'nameKey' => 'number-grid',
                                'descriptionKey' => 'number-grid',
                                'img' => '/img/game/number-grid.png',
                                'icon' => 'mdi-numeric',
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
                'img' => '/img/game/arcade-game.png',
                'icon' => 'mdi-gamepad-variant-outline',
                'subCategories' => [
                    [
                        'id' => 'reflex-arcade',
                        'nameKey' => 'reflex-arcade',
                        'descriptionKey' => 'reflex-arcade',
                        'img' => '/img/game/reflex-arcade.png',
                        'icon' => 'mdi-flash-outline',
                        'games' => [
                            [
                                'id' => 'color-reactor',
                                'nameKey' => 'color-reactor',
                                'descriptionKey' => 'color-reactor',
                                'img' => '/img/game/color-reactor.png',
                                'icon' => 'mdi-palette-outline',
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
                        'img' => '/img/game/runner-arcade.png',
                        'icon' => 'mdi-run-fast',
                        'games' => [
                            [
                                'id' => 'sky-run',
                                'nameKey' => 'sky-run',
                                'descriptionKey' => 'sky-run',
                                'img' => '/img/game/sky-run.png',
                                'icon' => 'mdi-run-fast',
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

        $imgIconTree = static function (array $categories): array {
            return array_map(static function (array $category): array {
                return [
                    'id' => $category['id'],
                    'img' => $category['img'],
                    'icon' => $category['icon'],
                    'subCategories' => array_map(static function (array $subCategory): array {
                        return [
                            'id' => $subCategory['id'],
                            'img' => $subCategory['img'],
                            'icon' => $subCategory['icon'],
                            'games' => array_map(static function (array $game): array {
                                return [
                                    'id' => $game['id'],
                                    'img' => $game['img'],
                                    'icon' => $game['icon'],
                                ];
                            }, $subCategory['games']),
                        ];
                    }, $category['subCategories']),
                ];
            }, $categories);
        };

        self::assertSame($imgIconTree($expectedPayload), $imgIconTree($payload));

        // Sensitive cases
        self::assertSame('/img/game/card-game.png', $payload[0]['img']);
        self::assertSame('mdi-cards-playing-outline', $payload[0]['icon']);
        self::assertSame('/img/game/board-game.png', $payload[1]['img']);
        self::assertSame('mdi-checkerboard', $payload[1]['icon']);
        self::assertSame('/img/game/smart-game.png', $payload[2]['img']);
        self::assertSame('mdi-brain', $payload[2]['icon']);
        self::assertSame('/img/game/arcade-game.png', $payload[3]['img']);
        self::assertSame('mdi-gamepad-variant-outline', $payload[3]['icon']);
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
