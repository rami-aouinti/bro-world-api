<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class PublicGameCatalogFixtureIntegrationTest extends WebTestCase
{

    /**
     * @var array<string, string>
     */
    private const UUID_BY_KEY = [
        'cards' => '21000000-0000-1000-8000-000000000001',
        'board' => '21000000-0000-1000-8000-000000000002',
        'smart-games' => '21000000-0000-1000-8000-000000000003',
        'arcade' => '21000000-0000-1000-8000-000000000004',
        'classic-cards' => '21000000-0000-1000-8000-000000000011',
        'party-cards' => '21000000-0000-1000-8000-000000000012',
        'table-classic' => '21000000-0000-1000-8000-000000000013',
        'family-board' => '21000000-0000-1000-8000-000000000014',
        'logic' => '21000000-0000-1000-8000-000000000015',
        'words-language' => '21000000-0000-1000-8000-000000000016',
        'grids-puzzles' => '21000000-0000-1000-8000-000000000017',
        'brain-training' => '21000000-0000-1000-8000-000000000018',
        'reaction-arcade' => '21000000-0000-1000-8000-000000000019',
        'classic-arcade' => '21000000-0000-1000-8000-000000000020',
        'rami' => '21000000-0000-1000-8000-000000000101',
        'belote' => '21000000-0000-1000-8000-000000000102',
        'poker' => '21000000-0000-1000-8000-000000000103',
        'uno' => '21000000-0000-1000-8000-000000000104',
        'solitaire' => '21000000-0000-1000-8000-000000000105',
        'hearts' => '21000000-0000-1000-8000-000000000106',
        'spades' => '21000000-0000-1000-8000-000000000107',
        'checkers' => '21000000-0000-1000-8000-000000000108',
        'chess' => '21000000-0000-1000-8000-000000000109',
        'ludo' => '21000000-0000-1000-8000-000000000110',
        'backgammon' => '21000000-0000-1000-8000-000000000111',
        'dominoes' => '21000000-0000-1000-8000-000000000112',
        'sudoku' => '21000000-0000-1000-8000-000000000113',
        'game-2048' => '21000000-0000-1000-8000-000000000114',
        'hidden-word' => '21000000-0000-1000-8000-000000000115',
        'nonogram' => '21000000-0000-1000-8000-000000000116',
        'memory-match' => '21000000-0000-1000-8000-000000000117',
        'mastermind' => '21000000-0000-1000-8000-000000000118',
        'minesweeper' => '21000000-0000-1000-8000-000000000119',
        'flappy-rocket' => '21000000-0000-1000-8000-000000000120',
        'stack-jump' => '21000000-0000-1000-8000-000000000121',
        'space-invaders' => '21000000-0000-1000-8000-000000000122',
        'brick-breaker' => '21000000-0000-1000-8000-000000000123',
        'snake' => '21000000-0000-1000-8000-000000000124',
    ];
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

        $expectedPayload = $this->expectedPayload();

        self::assertSame($expectedPayload, $payload);
        self::assertSame($this->extractImgIconTree($expectedPayload), $this->extractImgIconTree($payload));

        self::assertSame('/img/game/card-game.png', $payload[0]['img']);
        self::assertSame('mdi-cards-playing-outline', $payload[0]['icon']);
        self::assertSame('/img/game/board-game.png', $payload[1]['img']);
        self::assertSame('mdi-checkerboard', $payload[1]['icon']);
        self::assertSame('/img/game/smart-game.png', $payload[2]['img']);
        self::assertSame('mdi-brain', $payload[2]['icon']);
        self::assertSame('/img/game/arcade-game.png', $payload[3]['img']);
        self::assertSame('mdi-gamepad-variant-outline', $payload[3]['icon']);
    }

    #[TestDox('Public game catalog endpoint is accessible without authentication.')]
    public function testPublicGameCatalogIsPublicWithoutAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/public/game-catalog');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractImgIconTree(array $categories): array
    {
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
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expectedPayload(): array
    {
        $payload = [
            [
                'id' => 'cards',
                'nameKey' => 'gamePage.catalog.categories.cards.name',
                'descriptionKey' => 'gamePage.catalog.categories.cards.description',
                'img' => '/img/game/card-game.png',
                'icon' => 'mdi-cards-playing-outline',
                'subCategories' => [
                    [
                        'id' => 'classic-cards',
                        'nameKey' => 'gamePage.catalog.subCategories.classicCards.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.classicCards.description',
                        'img' => '/img/game/classic-card.png',
                        'icon' => 'mdi-cards-outline',
                        'games' => [
                            ['id' => 'rami', 'nameKey' => 'gamePage.catalog.games.rami.name', 'descriptionKey' => 'gamePage.catalog.games.rami.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-diamond-outline', 'component' => 'rami', 'supportedModes' => ['ai', 'pvp'], 'categoryKey' => 'cards', 'subcategoryKey' => 'classic-cards'],
                            ['id' => 'belote', 'nameKey' => 'gamePage.catalog.games.belote.name', 'descriptionKey' => 'gamePage.catalog.games.belote.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-club-outline', 'component' => 'belote', 'supportedModes' => ['ai'], 'categoryKey' => 'cards', 'subcategoryKey' => 'classic-cards'],
                            ['id' => 'poker', 'nameKey' => 'gamePage.catalog.games.poker.name', 'descriptionKey' => 'gamePage.catalog.games.poker.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-spade-outline', 'component' => 'poker', 'supportedModes' => ['ai'], 'categoryKey' => 'cards', 'subcategoryKey' => 'classic-cards'],
                            ['id' => 'uno', 'nameKey' => 'gamePage.catalog.games.uno.name', 'descriptionKey' => 'gamePage.catalog.games.uno.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-playing', 'component' => 'uno', 'supportedModes' => ['ai', 'pvp'], 'categoryKey' => 'cards', 'subcategoryKey' => 'classic-cards'],
                        ],
                    ],
                    [
                        'id' => 'party-cards',
                        'nameKey' => 'gamePage.catalog.subCategories.partyCards.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.partyCards.description',
                        'img' => '/img/game/party-card.png',
                        'icon' => 'mdi-party-popper',
                        'games' => [
                            ['id' => 'solitaire', 'nameKey' => 'gamePage.catalog.games.solitaire.name', 'descriptionKey' => 'gamePage.catalog.games.solitaire.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-playing-heart-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'cards', 'subcategoryKey' => 'party-cards'],
                            ['id' => 'hearts', 'nameKey' => 'gamePage.catalog.games.hearts.name', 'descriptionKey' => 'gamePage.catalog.games.hearts.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-heart', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'cards', 'subcategoryKey' => 'party-cards'],
                            ['id' => 'spades', 'nameKey' => 'gamePage.catalog.games.spades.name', 'descriptionKey' => 'gamePage.catalog.games.spades.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-spade-heart-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'cards', 'subcategoryKey' => 'party-cards'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'board',
                'nameKey' => 'gamePage.catalog.categories.board.name',
                'descriptionKey' => 'gamePage.catalog.categories.board.description',
                'img' => '/img/game/board-game.png',
                'icon' => 'mdi-checkerboard',
                'subCategories' => [
                    [
                        'id' => 'table-classic',
                        'nameKey' => 'gamePage.catalog.subCategories.tableClassic.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.tableClassic.description',
                        'img' => '/img/game/card-game.png',
                        'icon' => 'mdi-gamepad-round-outline',
                        'games' => [
                            ['id' => 'checkers', 'nameKey' => 'gamePage.catalog.games.checkers.name', 'descriptionKey' => 'gamePage.catalog.games.checkers.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-circle-multiple-outline', 'component' => 'checkers', 'supportedModes' => ['ai', 'pvp'], 'categoryKey' => 'board', 'subcategoryKey' => 'table-classic'],
                            ['id' => 'chess', 'nameKey' => 'gamePage.catalog.games.chess.name', 'descriptionKey' => 'gamePage.catalog.games.chess.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-chess-knight', 'component' => 'chess', 'supportedModes' => ['ai', 'pvp'], 'categoryKey' => 'gamePage.catalog.categories.board.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.tableClassic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.hard', 'tags' => ['gamePage.catalog.games.chess.meta.tags.strategy', 'gamePage.catalog.games.chess.meta.tags.solo', 'gamePage.catalog.games.chess.meta.tags.multiplayer', 'gamePage.catalog.games.chess.meta.tags.oneVsOne', 'gamePage.catalog.games.chess.meta.tags.ai', 'gamePage.catalog.games.chess.meta.tags.replay'], 'features' => ['gamePage.catalog.games.chess.meta.features.ai', 'gamePage.catalog.games.chess.meta.features.multiplayer', 'gamePage.catalog.games.chess.meta.features.replay']],
                        ],
                    ],
                    [
                        'id' => 'family-board',
                        'nameKey' => 'gamePage.catalog.subCategories.familyBoard.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.familyBoard.description',
                        'img' => '/img/game/family-board.png',
                        'icon' => 'mdi-account-group-outline',
                        'games' => [
                            ['id' => 'ludo', 'nameKey' => 'gamePage.catalog.games.ludo.name', 'descriptionKey' => 'gamePage.catalog.games.ludo.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-dice-multiple-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'board', 'subcategoryKey' => 'family-board'],
                            ['id' => 'backgammon', 'nameKey' => 'gamePage.catalog.games.backgammon.name', 'descriptionKey' => 'gamePage.catalog.games.backgammon.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-gamepad-variant-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'board', 'subcategoryKey' => 'family-board'],
                            ['id' => 'dominoes', 'nameKey' => 'gamePage.catalog.games.dominoes.name', 'descriptionKey' => 'gamePage.catalog.games.dominoes.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-domino-mask', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'board', 'subcategoryKey' => 'family-board'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'smart-games',
                'nameKey' => 'gamePage.catalog.categories.smartGames.name',
                'descriptionKey' => 'gamePage.catalog.categories.smartGames.description',
                'img' => '/img/game/smart-game.png',
                'icon' => 'mdi-brain',
                'subCategories' => [
                    [
                        'id' => 'logic',
                        'nameKey' => 'gamePage.catalog.subCategories.logic.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.logic.description',
                        'img' => '/img/game/logic.png',
                        'icon' => 'mdi-lightbulb-on-outline',
                        'games' => [
                            ['id' => 'sudoku', 'nameKey' => 'gamePage.catalog.games.sudoku.name', 'descriptionKey' => 'gamePage.catalog.games.sudoku.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-grid', 'component' => 'sudoku', 'supportedModes' => ['ai'], 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.logic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.medium', 'tags' => ['gamePage.catalog.games.sudoku.meta.tags.logic', 'gamePage.catalog.games.sudoku.meta.tags.puzzle', 'gamePage.catalog.games.sudoku.meta.tags.daily', 'gamePage.catalog.games.sudoku.meta.tags.solo', 'gamePage.catalog.games.sudoku.meta.tags.timer', 'gamePage.catalog.games.sudoku.meta.tags.score'], 'features' => ['gamePage.catalog.games.sudoku.meta.features.gridGeneration', 'gamePage.catalog.games.sudoku.meta.features.autoCheck', 'gamePage.catalog.games.sudoku.meta.features.timerAndScore']],
                            ['id' => 'game-2048', 'nameKey' => 'gamePage.catalog.games.game2048.name', 'descriptionKey' => 'gamePage.catalog.games.game2048.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-numeric-8-box-multiple-outline', 'component' => 'game2048', 'supportedModes' => ['ai'], 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.logic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.easy', 'tags' => ['gamePage.catalog.games.game2048.meta.tags.logic', 'gamePage.catalog.games.game2048.meta.tags.strategy', 'gamePage.catalog.games.game2048.meta.tags.puzzle', 'gamePage.catalog.games.game2048.meta.tags.solo', 'gamePage.catalog.games.game2048.meta.tags.score'], 'features' => ['gamePage.catalog.games.game2048.meta.features.smoothAnimations', 'gamePage.catalog.games.game2048.meta.features.scoreAndBest', 'gamePage.catalog.games.game2048.meta.features.sessionSave']],
                        ],
                    ],
                    [
                        'id' => 'words-language',
                        'nameKey' => 'gamePage.catalog.subCategories.wordsLanguage.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.wordsLanguage.description',
                        'img' => '/img/game/words.png',
                        'icon' => 'mdi-alphabetical-variant',
                        'games' => [
                            ['id' => 'hidden-word', 'nameKey' => 'gamePage.catalog.games.hiddenWord.name', 'descriptionKey' => 'gamePage.catalog.games.hiddenWord.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-text-search-variant', 'component' => 'hidden-word', 'supportedModes' => ['ai'], 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.wordsLanguage.name', 'difficultyKey' => 'gamePage.catalog.difficulties.medium', 'tags' => ['gamePage.catalog.games.hiddenWord.meta.tags.words', 'gamePage.catalog.games.hiddenWord.meta.tags.daily', 'gamePage.catalog.games.hiddenWord.meta.tags.puzzle', 'gamePage.catalog.games.hiddenWord.meta.tags.solo', 'gamePage.catalog.games.hiddenWord.meta.tags.hints', 'gamePage.catalog.games.hiddenWord.meta.tags.share'], 'features' => ['gamePage.catalog.games.hiddenWord.meta.features.wordOfTheDay', 'gamePage.catalog.games.hiddenWord.meta.features.dictionary', 'gamePage.catalog.games.hiddenWord.meta.features.share']],
                        ],
                    ],
                    [
                        'id' => 'grids-puzzles',
                        'nameKey' => 'gamePage.catalog.subCategories.gridsPuzzles.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.gridsPuzzles.description',
                        'img' => '/img/game/puzzle.png',
                        'icon' => 'mdi-grid-large',
                        'games' => [
                            ['id' => 'nonogram', 'nameKey' => 'gamePage.catalog.games.nonogram.name', 'descriptionKey' => 'gamePage.catalog.games.nonogram.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-view-grid-plus-outline', 'component' => 'nonogram', 'supportedModes' => ['ai'], 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.gridsPuzzles.name', 'difficultyKey' => 'gamePage.catalog.difficulties.hard', 'tags' => ['gamePage.catalog.games.nonogram.meta.tags.logic', 'gamePage.catalog.games.nonogram.meta.tags.puzzle', 'gamePage.catalog.games.nonogram.meta.tags.grid', 'gamePage.catalog.games.nonogram.meta.tags.solo', 'gamePage.catalog.games.nonogram.meta.tags.deduction'], 'features' => ['gamePage.catalog.games.nonogram.meta.features.rowColumnHints', 'gamePage.catalog.games.nonogram.meta.features.progressiveLevels']],
                        ],
                    ],
                    [
                        'id' => 'brain-training',
                        'nameKey' => 'gamePage.catalog.subCategories.brainTraining.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.brainTraining.description',
                        'img' => '/img/game/brain.png',
                        'icon' => 'mdi-head-cog-outline',
                        'games' => [
                            ['id' => 'memory-match', 'nameKey' => 'gamePage.catalog.games.memoryMatch.name', 'descriptionKey' => 'gamePage.catalog.games.memoryMatch.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-brain', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'smart-games', 'subcategoryKey' => 'brain-training'],
                            ['id' => 'mastermind', 'nameKey' => 'gamePage.catalog.games.mastermind.name', 'descriptionKey' => 'gamePage.catalog.games.mastermind.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-bullseye-arrow', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'smart-games', 'subcategoryKey' => 'brain-training'],
                            ['id' => 'minesweeper', 'nameKey' => 'gamePage.catalog.games.minesweeper.name', 'descriptionKey' => 'gamePage.catalog.games.minesweeper.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-bomb', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'smart-games', 'subcategoryKey' => 'brain-training'],
                        ],
                    ],
                ],
            ],
            [
                'id' => 'arcade',
                'nameKey' => 'gamePage.catalog.categories.arcade.name',
                'descriptionKey' => 'gamePage.catalog.categories.arcade.description',
                'img' => '/img/game/arcade-game.png',
                'icon' => 'mdi-gamepad-variant-outline',
                'subCategories' => [
                    [
                        'id' => 'reaction-arcade',
                        'nameKey' => 'gamePage.catalog.subCategories.reactionArcade.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.reactionArcade.description',
                        'img' => '/img/game/card-game.png',
                        'icon' => 'mdi-lightning-bolt-outline',
                        'games' => [
                            ['id' => 'flappy-rocket', 'nameKey' => 'gamePage.catalog.games.flappyRocket.name', 'descriptionKey' => 'gamePage.catalog.games.flappyRocket.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-rocket-launch-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'arcade', 'subcategoryKey' => 'reaction-arcade'],
                            ['id' => 'stack-jump', 'nameKey' => 'gamePage.catalog.games.stackJump.name', 'descriptionKey' => 'gamePage.catalog.games.stackJump.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cube-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'arcade', 'subcategoryKey' => 'reaction-arcade'],
                        ],
                    ],
                    [
                        'id' => 'classic-arcade',
                        'nameKey' => 'gamePage.catalog.subCategories.classicArcade.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.classicArcade.description',
                        'img' => '/img/game/card-game.png',
                        'icon' => 'mdi-ghost-outline',
                        'games' => [
                            ['id' => 'space-invaders', 'nameKey' => 'gamePage.catalog.games.spaceInvaders.name', 'descriptionKey' => 'gamePage.catalog.games.spaceInvaders.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-space-invaders', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'arcade', 'subcategoryKey' => 'classic-arcade'],
                            ['id' => 'brick-breaker', 'nameKey' => 'gamePage.catalog.games.brickBreaker.name', 'descriptionKey' => 'gamePage.catalog.games.brickBreaker.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-view-grid-plus-outline', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'arcade', 'subcategoryKey' => 'classic-arcade'],
                            ['id' => 'snake', 'nameKey' => 'gamePage.catalog.games.snake.name', 'descriptionKey' => 'gamePage.catalog.games.snake.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-snake', 'component' => null, 'supportedModes' => [], 'categoryKey' => 'arcade', 'subcategoryKey' => 'classic-arcade'],
                        ],
                    ],
                ],
            ],
        ];

        return $this->withUuidAndKey($payload);
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array<int, array<string, mixed>>
     */
    private function withUuidAndKey(array $categories): array
    {
        return array_map(function (array $category): array {
            $categoryKey = $category['id'];
            $category['id'] = self::UUID_BY_KEY[$categoryKey];
            $category['key'] = $categoryKey;

            $category['subCategories'] = array_map(function (array $subCategory): array {
                $subCategoryKey = $subCategory['id'];
                $subCategory['id'] = self::UUID_BY_KEY[$subCategoryKey];
                $subCategory['key'] = $subCategoryKey;

                $subCategory['games'] = array_map(function (array $game): array {
                    $gameKey = $game['id'];
                    $game['id'] = self::UUID_BY_KEY[$gameKey];
                    $game['key'] = $gameKey;

                    return $game;
                }, $subCategory['games']);

                return $subCategory;
            }, $category['subCategories']);

            return $category;
        }, $categories);
    }
}

