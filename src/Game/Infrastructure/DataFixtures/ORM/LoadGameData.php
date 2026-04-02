<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\DataFixtures\ORM;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameLevelOption;
use App\Game\Domain\Entity\GameLevelCost;
use App\Game\Domain\Entity\GameSubCategory;
use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;
use App\Game\Domain\Enum\UserGameLevel;
use App\General\Domain\Rest\UuidHelper;
use App\Tests\Utils\PhpUnitUtil;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;
use Throwable;

final class LoadGameData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, non-empty-string>
     */
    public static array $uuids = [
        'category-cards' => '21000000-0000-1000-8000-000000000001',
        'category-board' => '21000000-0000-1000-8000-000000000002',
        'category-smart-games' => '21000000-0000-1000-8000-000000000003',
        'category-arcade' => '21000000-0000-1000-8000-000000000004',

        'subcategory-classic-cards' => '21000000-0000-1000-8000-000000000011',
        'subcategory-party-cards' => '21000000-0000-1000-8000-000000000012',
        'subcategory-table-classic' => '21000000-0000-1000-8000-000000000013',
        'subcategory-family-board' => '21000000-0000-1000-8000-000000000014',
        'subcategory-logic' => '21000000-0000-1000-8000-000000000015',
        'subcategory-words-language' => '21000000-0000-1000-8000-000000000016',
        'subcategory-grids-puzzles' => '21000000-0000-1000-8000-000000000017',
        'subcategory-brain-training' => '21000000-0000-1000-8000-000000000018',
        'subcategory-reaction-arcade' => '21000000-0000-1000-8000-000000000019',
        'subcategory-classic-arcade' => '21000000-0000-1000-8000-000000000020',

        'level-beginner' => '21000000-0000-1000-8000-000000000021',
        'level-intermediate' => '21000000-0000-1000-8000-000000000022',
        'level-advanced' => '21000000-0000-1000-8000-000000000023',

        'game-rami' => '21000000-0000-1000-8000-000000000101',
        'game-belote' => '21000000-0000-1000-8000-000000000102',
        'game-poker' => '21000000-0000-1000-8000-000000000103',
        'game-uno' => '21000000-0000-1000-8000-000000000104',
        'game-solitaire' => '21000000-0000-1000-8000-000000000105',
        'game-hearts' => '21000000-0000-1000-8000-000000000106',
        'game-spades' => '21000000-0000-1000-8000-000000000107',
        'game-checkers' => '21000000-0000-1000-8000-000000000108',
        'game-chess' => '21000000-0000-1000-8000-000000000109',
        'game-ludo' => '21000000-0000-1000-8000-000000000110',
        'game-backgammon' => '21000000-0000-1000-8000-000000000111',
        'game-dominoes' => '21000000-0000-1000-8000-000000000112',
        'game-sudoku' => '21000000-0000-1000-8000-000000000113',
        'game-game-2048' => '21000000-0000-1000-8000-000000000114',
        'game-hidden-word' => '21000000-0000-1000-8000-000000000115',
        'game-nonogram' => '21000000-0000-1000-8000-000000000116',
        'game-memory-match' => '21000000-0000-1000-8000-000000000117',
        'game-mastermind' => '21000000-0000-1000-8000-000000000118',
        'game-minesweeper' => '21000000-0000-1000-8000-000000000119',
        'game-flappy-rocket' => '21000000-0000-1000-8000-000000000120',
        'game-stack-jump' => '21000000-0000-1000-8000-000000000121',
        'game-space-invaders' => '21000000-0000-1000-8000-000000000122',
        'game-brick-breaker' => '21000000-0000-1000-8000-000000000123',
        'game-snake' => '21000000-0000-1000-8000-000000000124',
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $coinsByLevel = [
            ['level' => UserGameLevel::EASY, 'coins' => 200],
            ['level' => UserGameLevel::MEDIUM, 'coins' => 400],
            ['level' => UserGameLevel::HARD, 'coins' => 600],
        ];

        $levels = [
            'beginner' => ['BEGINNER', 'Beginner', 'Découverte et prise en main rapide.'],
            'intermediate' => ['INTERMEDIATE', 'Intermediate', 'Mécaniques plus riches pour joueurs réguliers.'],
            'advanced' => ['ADVANCED', 'Advanced', 'Défi élevé avec optimisation et stratégie.'],
        ];

        foreach ($levels as $key => [$value, $label, $description]) {
            $level = (new GameLevelOption())
                ->setValue($value)
                ->setLabel($label)
                ->setDescription($description);
            $this->forceUuid($level, 'level-' . $key);

            $manager->persist($level);
            $this->addReference('GameLevel-' . $key, $level);
        }

        $categories = [
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
                            ['id' => 'rami', 'nameKey' => 'gamePage.catalog.games.rami.name', 'descriptionKey' => 'gamePage.catalog.games.rami.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-diamond-outline', 'component' => 'rami', 'supportedModes' => ['ai', 'pvp']],
                            ['id' => 'belote', 'nameKey' => 'gamePage.catalog.games.belote.name', 'descriptionKey' => 'gamePage.catalog.games.belote.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-club-outline', 'component' => 'belote', 'supportedModes' => ['ai']],
                            ['id' => 'poker', 'nameKey' => 'gamePage.catalog.games.poker.name', 'descriptionKey' => 'gamePage.catalog.games.poker.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-spade-outline', 'component' => 'poker', 'supportedModes' => ['ai']],
                            ['id' => 'uno', 'nameKey' => 'gamePage.catalog.games.uno.name', 'descriptionKey' => 'gamePage.catalog.games.uno.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-playing', 'component' => 'uno', 'supportedModes' => ['ai', 'pvp']],
                        ],
                    ],
                    [
                        'id' => 'party-cards',
                        'nameKey' => 'gamePage.catalog.subCategories.partyCards.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.partyCards.description',
                        'img' => '/img/game/party-card.png',
                        'icon' => 'mdi-party-popper',
                        'games' => [
                            ['id' => 'solitaire', 'nameKey' => 'gamePage.catalog.games.solitaire.name', 'descriptionKey' => 'gamePage.catalog.games.solitaire.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-playing-heart-outline', 'component' => null, 'supportedModes' => []],
                            ['id' => 'hearts', 'nameKey' => 'gamePage.catalog.games.hearts.name', 'descriptionKey' => 'gamePage.catalog.games.hearts.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-heart', 'component' => null, 'supportedModes' => []],
                            ['id' => 'spades', 'nameKey' => 'gamePage.catalog.games.spades.name', 'descriptionKey' => 'gamePage.catalog.games.spades.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-spade-heart-outline', 'component' => null, 'supportedModes' => []],
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
                            ['id' => 'checkers', 'nameKey' => 'gamePage.catalog.games.checkers.name', 'descriptionKey' => 'gamePage.catalog.games.checkers.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-circle-multiple-outline', 'component' => 'checkers', 'supportedModes' => ['ai', 'pvp']],
                            ['id' => 'chess', 'categoryKey' => 'gamePage.catalog.categories.board.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.tableClassic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.hard', 'tags' => ['gamePage.catalog.games.chess.meta.tags.strategy', 'gamePage.catalog.games.chess.meta.tags.solo', 'gamePage.catalog.games.chess.meta.tags.multiplayer', 'gamePage.catalog.games.chess.meta.tags.oneVsOne', 'gamePage.catalog.games.chess.meta.tags.ai', 'gamePage.catalog.games.chess.meta.tags.replay'], 'nameKey' => 'gamePage.catalog.games.chess.name', 'descriptionKey' => 'gamePage.catalog.games.chess.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-chess-knight', 'component' => 'chess', 'supportedModes' => ['ai', 'pvp'], 'features' => ['gamePage.catalog.games.chess.meta.features.ai', 'gamePage.catalog.games.chess.meta.features.multiplayer', 'gamePage.catalog.games.chess.meta.features.replay']],
                        ],
                    ],
                    [
                        'id' => 'family-board',
                        'nameKey' => 'gamePage.catalog.subCategories.familyBoard.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.familyBoard.description',
                        'img' => '/img/game/family-board.png',
                        'icon' => 'mdi-account-group-outline',
                        'games' => [
                            ['id' => 'ludo', 'nameKey' => 'gamePage.catalog.games.ludo.name', 'descriptionKey' => 'gamePage.catalog.games.ludo.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-dice-multiple-outline', 'component' => null, 'supportedModes' => []],
                            ['id' => 'backgammon', 'nameKey' => 'gamePage.catalog.games.backgammon.name', 'descriptionKey' => 'gamePage.catalog.games.backgammon.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-gamepad-variant-outline', 'component' => null, 'supportedModes' => []],
                            ['id' => 'dominoes', 'nameKey' => 'gamePage.catalog.games.dominoes.name', 'descriptionKey' => 'gamePage.catalog.games.dominoes.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-domino-mask', 'component' => null, 'supportedModes' => []],
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
                            ['id' => 'sudoku', 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.logic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.medium', 'tags' => ['gamePage.catalog.games.sudoku.meta.tags.logic', 'gamePage.catalog.games.sudoku.meta.tags.puzzle', 'gamePage.catalog.games.sudoku.meta.tags.daily', 'gamePage.catalog.games.sudoku.meta.tags.solo', 'gamePage.catalog.games.sudoku.meta.tags.timer', 'gamePage.catalog.games.sudoku.meta.tags.score'], 'nameKey' => 'gamePage.catalog.games.sudoku.name', 'descriptionKey' => 'gamePage.catalog.games.sudoku.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-grid', 'component' => 'sudoku', 'supportedModes' => ['ai'], 'features' => ['gamePage.catalog.games.sudoku.meta.features.gridGeneration', 'gamePage.catalog.games.sudoku.meta.features.autoCheck', 'gamePage.catalog.games.sudoku.meta.features.timerAndScore']],
                            ['id' => 'game-2048', 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.logic.name', 'difficultyKey' => 'gamePage.catalog.difficulties.easy', 'tags' => ['gamePage.catalog.games.game2048.meta.tags.logic', 'gamePage.catalog.games.game2048.meta.tags.strategy', 'gamePage.catalog.games.game2048.meta.tags.puzzle', 'gamePage.catalog.games.game2048.meta.tags.solo', 'gamePage.catalog.games.game2048.meta.tags.score'], 'nameKey' => 'gamePage.catalog.games.game2048.name', 'descriptionKey' => 'gamePage.catalog.games.game2048.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-numeric-8-box-multiple-outline', 'component' => 'game2048', 'supportedModes' => ['ai'], 'features' => ['gamePage.catalog.games.game2048.meta.features.smoothAnimations', 'gamePage.catalog.games.game2048.meta.features.scoreAndBest', 'gamePage.catalog.games.game2048.meta.features.sessionSave']],
                        ],
                    ],
                    [
                        'id' => 'words-language',
                        'nameKey' => 'gamePage.catalog.subCategories.wordsLanguage.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.wordsLanguage.description',
                        'img' => '/img/game/words.png',
                        'icon' => 'mdi-alphabetical-variant',
                        'games' => [
                            ['id' => 'hidden-word', 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.wordsLanguage.name', 'difficultyKey' => 'gamePage.catalog.difficulties.medium', 'tags' => ['gamePage.catalog.games.hiddenWord.meta.tags.words', 'gamePage.catalog.games.hiddenWord.meta.tags.daily', 'gamePage.catalog.games.hiddenWord.meta.tags.puzzle', 'gamePage.catalog.games.hiddenWord.meta.tags.solo', 'gamePage.catalog.games.hiddenWord.meta.tags.hints', 'gamePage.catalog.games.hiddenWord.meta.tags.share'], 'nameKey' => 'gamePage.catalog.games.hiddenWord.name', 'descriptionKey' => 'gamePage.catalog.games.hiddenWord.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-text-search-variant', 'component' => 'hidden-word', 'supportedModes' => ['ai'], 'features' => ['gamePage.catalog.games.hiddenWord.meta.features.wordOfTheDay', 'gamePage.catalog.games.hiddenWord.meta.features.dictionary', 'gamePage.catalog.games.hiddenWord.meta.features.share']],
                        ],
                    ],
                    [
                        'id' => 'grids-puzzles',
                        'nameKey' => 'gamePage.catalog.subCategories.gridsPuzzles.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.gridsPuzzles.description',
                        'img' => '/img/game/puzzle.png',
                        'icon' => 'mdi-grid-large',
                        'games' => [
                            ['id' => 'nonogram', 'categoryKey' => 'gamePage.catalog.categories.smartGames.name', 'subcategoryKey' => 'gamePage.catalog.subCategories.gridsPuzzles.name', 'difficultyKey' => 'gamePage.catalog.difficulties.hard', 'tags' => ['gamePage.catalog.games.nonogram.meta.tags.logic', 'gamePage.catalog.games.nonogram.meta.tags.puzzle', 'gamePage.catalog.games.nonogram.meta.tags.grid', 'gamePage.catalog.games.nonogram.meta.tags.solo', 'gamePage.catalog.games.nonogram.meta.tags.deduction'], 'nameKey' => 'gamePage.catalog.games.nonogram.name', 'descriptionKey' => 'gamePage.catalog.games.nonogram.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-view-grid-plus-outline', 'component' => 'nonogram', 'supportedModes' => ['ai'], 'features' => ['gamePage.catalog.games.nonogram.meta.features.rowColumnHints', 'gamePage.catalog.games.nonogram.meta.features.progressiveLevels']],
                        ],
                    ],
                    [
                        'id' => 'brain-training',
                        'nameKey' => 'gamePage.catalog.subCategories.brainTraining.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.brainTraining.description',
                        'img' => '/img/game/brain.png',
                        'icon' => 'mdi-head-cog-outline',
                        'games' => [
                            ['id' => 'memory-match', 'nameKey' => 'gamePage.catalog.games.memoryMatch.name', 'descriptionKey' => 'gamePage.catalog.games.memoryMatch.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-brain', 'component' => null, 'supportedModes' => []],
                            ['id' => 'mastermind', 'nameKey' => 'gamePage.catalog.games.mastermind.name', 'descriptionKey' => 'gamePage.catalog.games.mastermind.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-bullseye-arrow', 'component' => null, 'supportedModes' => []],
                            ['id' => 'minesweeper', 'nameKey' => 'gamePage.catalog.games.minesweeper.name', 'descriptionKey' => 'gamePage.catalog.games.minesweeper.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-bomb', 'component' => null, 'supportedModes' => []],
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
                            ['id' => 'flappy-rocket', 'nameKey' => 'gamePage.catalog.games.flappyRocket.name', 'descriptionKey' => 'gamePage.catalog.games.flappyRocket.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-rocket-launch-outline', 'component' => null, 'supportedModes' => []],
                            ['id' => 'stack-jump', 'nameKey' => 'gamePage.catalog.games.stackJump.name', 'descriptionKey' => 'gamePage.catalog.games.stackJump.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cube-outline', 'component' => null, 'supportedModes' => []],
                        ],
                    ],
                    [
                        'id' => 'classic-arcade',
                        'nameKey' => 'gamePage.catalog.subCategories.classicArcade.name',
                        'descriptionKey' => 'gamePage.catalog.subCategories.classicArcade.description',
                        'img' => '/img/game/card-game.png',
                        'icon' => 'mdi-ghost-outline',
                        'games' => [
                            ['id' => 'space-invaders', 'nameKey' => 'gamePage.catalog.games.spaceInvaders.name', 'descriptionKey' => 'gamePage.catalog.games.spaceInvaders.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-space-invaders', 'component' => null, 'supportedModes' => []],
                            ['id' => 'brick-breaker', 'nameKey' => 'gamePage.catalog.games.brickBreaker.name', 'descriptionKey' => 'gamePage.catalog.games.brickBreaker.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-view-grid-plus-outline', 'component' => null, 'supportedModes' => []],
                            ['id' => 'snake', 'nameKey' => 'gamePage.catalog.games.snake.name', 'descriptionKey' => 'gamePage.catalog.games.snake.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-snake', 'component' => null, 'supportedModes' => []],
                        ],
                    ],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $category = (new GameCategory())
                ->setKey($categoryData['id'])
                ->setNameKey($categoryData['nameKey'])
                ->setDescriptionKey($categoryData['descriptionKey'])
                ->setImg($categoryData['img'])
                ->setIcon($categoryData['icon']);
            $this->forceUuid($category, 'category-' . $categoryData['id']);

            $manager->persist($category);
            $this->addReference('GameCategory-' . $categoryData['id'], $category);

            foreach ($categoryData['subCategories'] as $subCategoryData) {
                $subCategory = (new GameSubCategory())
                    ->setCategory($category)
                    ->setKey($subCategoryData['id'])
                    ->setNameKey($subCategoryData['nameKey'])
                    ->setDescriptionKey($subCategoryData['descriptionKey'])
                    ->setImg($subCategoryData['img'])
                    ->setIcon($subCategoryData['icon']);
                $this->forceUuid($subCategory, 'subcategory-' . $subCategoryData['id']);

                $manager->persist($subCategory);
                $this->addReference('GameSubCategory-' . $subCategoryData['id'], $subCategory);

                foreach ($subCategoryData['games'] as $gameData) {
                    $game = (new Game())
                        ->setKey($gameData['id'])
                        ->setNameKey($gameData['nameKey'])
                        ->setDescriptionKey($gameData['descriptionKey'])
                        ->setImg($gameData['img'])
                        ->setIcon($gameData['icon'])
                        ->setCategory($category)
                        ->setSubCategory($subCategory)
                        ->setComponent($gameData['component'])
                        ->setSupportedModes($gameData['supportedModes'])
                        ->setStatus(GameStatus::ACTIVE)
                        ->setMetadata([]);

                    if (isset($gameData['categoryKey'])) {
                        $game->setCategoryKey($gameData['categoryKey']);
                    }

                    if (isset($gameData['subcategoryKey'])) {
                        $game->setSubcategoryKey($gameData['subcategoryKey']);
                    }

                    if (isset($gameData['difficultyKey'])) {
                        $game->setDifficultyKey($gameData['difficultyKey']);
                        if (str_contains($gameData['difficultyKey'], 'easy')) {
                            $game->setLevel(GameLevel::BEGINNER);
                        } elseif (str_contains($gameData['difficultyKey'], 'medium')) {
                            $game->setLevel(GameLevel::INTERMEDIATE);
                        } elseif (str_contains($gameData['difficultyKey'], 'hard')) {
                            $game->setLevel(GameLevel::ADVANCED);
                        }
                    }

                    if (isset($gameData['tags'])) {
                        $game->setTags($gameData['tags']);
                    }

                    if (isset($gameData['features'])) {
                        $game->setFeatures($gameData['features']);
                    }

                    $this->forceUuid($game, 'game-' . $gameData['id']);
                    $manager->persist($game);
                    $this->addReference('Game-' . $gameData['id'], $game);

                    foreach ($coinsByLevel as $coinsConfig) {
                        $level = $coinsConfig['level'];
                        $coins = $coinsConfig['coins'];
                        $gameLevelCost = (new GameLevelCost())
                            ->setGame($game)
                            ->setLevelKey($level)
                            ->setMinCoinsCost($coins)
                            ->setWinRewardCoins($coins)
                            ->setLosePenaltyCoins($coins);

                        $manager->persist($gameLevelCost);
                    }
                }
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 43;
    }

    /**
     * @throws Throwable
     */
    private function forceUuid(object $entity, string $uuidKey): void
    {
        PhpUnitUtil::setProperty('id', UuidHelper::fromString(self::$uuids[$uuidKey]), $entity);
    }
}
