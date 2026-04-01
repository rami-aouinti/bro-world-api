<?php

declare(strict_types=1);

namespace App\Game\Infrastructure\DataFixtures\ORM;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameLevelOption;
use App\Game\Domain\Entity\GameSubCategory;
use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;
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
        'subcategory-quick-cards' => '21000000-0000-1000-8000-000000000012',
        'subcategory-table-classic' => '21000000-0000-1000-8000-000000000013',
        'subcategory-strategy-board' => '21000000-0000-1000-8000-000000000014',
        'subcategory-words-language' => '21000000-0000-1000-8000-000000000015',
        'subcategory-logic-brain' => '21000000-0000-1000-8000-000000000016',
        'subcategory-reflex-arcade' => '21000000-0000-1000-8000-000000000017',
        'subcategory-runner-arcade' => '21000000-0000-1000-8000-000000000018',
        'level-beginner' => '21000000-0000-1000-8000-000000000021',
        'level-intermediate' => '21000000-0000-1000-8000-000000000022',
        'level-advanced' => '21000000-0000-1000-8000-000000000023',
        'game-solitaire-classic' => '21000000-0000-1000-8000-000000000101',
        'game-blackjack-classic' => '21000000-0000-1000-8000-000000000102',
        'game-speed-duel-cards' => '21000000-0000-1000-8000-000000000103',
        'game-checkers-table' => '21000000-0000-1000-8000-000000000104',
        'game-chess-table' => '21000000-0000-1000-8000-000000000105',
        'game-hexa-tactics' => '21000000-0000-1000-8000-000000000106',
        'game-word-link' => '21000000-0000-1000-8000-000000000107',
        'game-anagram-rush' => '21000000-0000-1000-8000-000000000108',
        'game-number-grid' => '21000000-0000-1000-8000-000000000109',
        'game-color-reactor' => '21000000-0000-1000-8000-000000000110',
        'game-sky-run' => '21000000-0000-1000-8000-000000000111',
    ];

    /**
     * @throws Throwable
     */
    #[Override]
    public function load(ObjectManager $manager): void
    {
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
            ['key' => 'cards', 'nameKey' => 'cards', 'descriptionKey' => 'cards', 'img' => '/img/game/card-game.png', 'icon' => 'mdi-cards-playing-outline'],
            ['key' => 'board', 'nameKey' => 'board', 'descriptionKey' => 'board', 'img' => '/img/game/board-game.png', 'icon' => 'mdi-checkerboard'],
            ['key' => 'smart-games', 'nameKey' => 'smart-games', 'descriptionKey' => 'smart-games', 'img' => '/img/game/smart-game.png', 'icon' => 'mdi-brain'],
            ['key' => 'arcade', 'nameKey' => 'arcade', 'descriptionKey' => 'arcade', 'img' => '/img/game/arcade-game.png', 'icon' => 'mdi-gamepad-variant-outline'],
        ];

        foreach ($categories as $item) {
            $category = (new GameCategory())
                ->setKey($item['key'])
                ->setNameKey($item['nameKey'])
                ->setDescriptionKey($item['descriptionKey'])
                ->setImg($item['img'])
                ->setIcon($item['icon']);
            $this->forceUuid($category, 'category-' . $item['key']);

            $manager->persist($category);
            $this->addReference('GameCategory-' . $item['key'], $category);
        }

        $subCategories = [
            ['key' => 'classic-cards', 'category' => 'cards', 'nameKey' => 'classic-cards', 'descriptionKey' => 'classic-cards', 'img' => '/img/game/classic-cards.png', 'icon' => 'mdi-cards-outline'],
            ['key' => 'quick-cards', 'category' => 'cards', 'nameKey' => 'quick-cards', 'descriptionKey' => 'quick-cards', 'img' => '/img/game/quick-cards.png', 'icon' => 'mdi-lightning-bolt-outline'],
            ['key' => 'table-classic', 'category' => 'board', 'nameKey' => 'table-classic', 'descriptionKey' => 'table-classic', 'img' => '/img/game/table-classic.png', 'icon' => 'mdi-checkerboard'],
            ['key' => 'strategy-board', 'category' => 'board', 'nameKey' => 'strategy-board', 'descriptionKey' => 'strategy-board', 'img' => '/img/game/strategy-board.png', 'icon' => 'mdi-chess-queen'],
            ['key' => 'words-language', 'category' => 'smart-games', 'nameKey' => 'words-language', 'descriptionKey' => 'words-language', 'img' => '/img/game/words-language.png', 'icon' => 'mdi-alphabetical-variant'],
            ['key' => 'logic-brain', 'category' => 'smart-games', 'nameKey' => 'logic-brain', 'descriptionKey' => 'logic-brain', 'img' => '/img/game/logic-brain.png', 'icon' => 'mdi-brain'],
            ['key' => 'reflex-arcade', 'category' => 'arcade', 'nameKey' => 'reflex-arcade', 'descriptionKey' => 'reflex-arcade', 'img' => '/img/game/reflex-arcade.png', 'icon' => 'mdi-flash-outline'],
            ['key' => 'runner-arcade', 'category' => 'arcade', 'nameKey' => 'runner-arcade', 'descriptionKey' => 'runner-arcade', 'img' => '/img/game/runner-arcade.png', 'icon' => 'mdi-run-fast'],
        ];

        foreach ($subCategories as $item) {
            $subCategory = (new GameSubCategory())
                ->setCategory($this->getReference('GameCategory-' . $item['category'], GameCategory::class))
                ->setKey($item['key'])
                ->setNameKey($item['nameKey'])
                ->setDescriptionKey($item['descriptionKey'])
                ->setImg($item['img'])
                ->setIcon($item['icon']);
            $this->forceUuid($subCategory, 'subcategory-' . $item['key']);

            $manager->persist($subCategory);
            $this->addReference('GameSubCategory-' . $item['key'], $subCategory);
        }

        $games = [
            [
                'key' => 'solitaire-classic',
                'nameKey' => 'solitaire-classic',
                'descriptionKey' => 'solitaire-classic',
                'img' => '/img/game/solitaire-classic.png',
                'icon' => 'mdi-cards-playing-outline',
                'category' => 'cards',
                'subcategory' => 'classic-cards',
                'component' => null,
                'supportedModes' => ['solo'],
                'categoryKey' => 'cards',
                'subcategoryKey' => 'classic-cards',
                'difficultyKey' => 'beginner',
                'tags' => ['cards', 'solo'],
                'features' => ['timer'],
                'level' => GameLevel::BEGINNER,
            ],
            [
                'key' => 'blackjack-classic',
                'nameKey' => 'blackjack-classic',
                'descriptionKey' => 'blackjack-classic',
                'img' => '/img/game/blackjack-classic.png',
                'icon' => 'mdi-cards-playing-spade-outline',
                'category' => 'cards',
                'subcategory' => 'classic-cards',
                'component' => 'BlackjackClassic',
                'supportedModes' => ['solo', 'versus'],
                'categoryKey' => 'cards',
                'subcategoryKey' => 'classic-cards',
                'difficultyKey' => 'intermediate',
                'tags' => ['cards', 'casino'],
                'features' => ['score-multiplier'],
                'level' => GameLevel::INTERMEDIATE,
            ],
            [
                'key' => 'speed-duel-cards',
                'nameKey' => 'speed-duel-cards',
                'descriptionKey' => 'speed-duel-cards',
                'img' => '/img/game/speed-duel-cards.png',
                'icon' => 'mdi-lightning-bolt-outline',
                'category' => 'cards',
                'subcategory' => 'quick-cards',
                'component' => null,
                'supportedModes' => [],
                'categoryKey' => 'cards',
                'subcategoryKey' => 'quick-cards',
                'difficultyKey' => null,
                'tags' => [],
                'features' => [],
                'level' => GameLevel::INTERMEDIATE,
            ],
            [
                'key' => 'checkers-table',
                'nameKey' => 'checkers-table',
                'descriptionKey' => 'checkers-table',
                'img' => '/img/game/checkers-table.png',
                'icon' => 'mdi-checkerboard',
                'category' => 'board',
                'subcategory' => 'table-classic',
                'component' => 'CheckersTable',
                'supportedModes' => ['versus'],
                'categoryKey' => 'board',
                'subcategoryKey' => 'table-classic',
                'difficultyKey' => 'beginner',
                'tags' => ['board'],
                'features' => ['ranked'],
                'level' => GameLevel::BEGINNER,
            ],
            [
                'key' => 'chess-table',
                'nameKey' => 'chess-table',
                'descriptionKey' => 'chess-table',
                'img' => '/img/game/chess-table.png',
                'icon' => 'mdi-chess-king',
                'category' => 'board',
                'subcategory' => 'table-classic',
                'component' => 'ChessTable',
                'supportedModes' => ['versus', 'online'],
                'categoryKey' => 'board',
                'subcategoryKey' => 'table-classic',
                'difficultyKey' => 'advanced',
                'tags' => ['board', 'strategy'],
                'features' => ['elo', 'analysis'],
                'level' => GameLevel::ADVANCED,
            ],
            [
                'key' => 'hexa-tactics',
                'nameKey' => 'hexa-tactics',
                'descriptionKey' => 'hexa-tactics',
                'img' => '/img/game/hexa-tactics.png',
                'icon' => 'mdi-hexagon-multiple-outline',
                'category' => 'board',
                'subcategory' => 'strategy-board',
                'component' => null,
                'supportedModes' => ['solo', 'versus'],
                'categoryKey' => 'board',
                'subcategoryKey' => 'strategy-board',
                'difficultyKey' => 'advanced',
                'tags' => ['board', 'tactics'],
                'features' => ['campaign'],
                'level' => GameLevel::ADVANCED,
            ],
            [
                'key' => 'word-link',
                'nameKey' => 'word-link',
                'descriptionKey' => 'word-link',
                'img' => '/img/game/word-link.png',
                'icon' => 'mdi-alphabetical-variant',
                'category' => 'smart-games',
                'subcategory' => 'words-language',
                'component' => 'WordLink',
                'supportedModes' => ['solo'],
                'categoryKey' => 'smart-games',
                'subcategoryKey' => 'words-language',
                'difficultyKey' => 'beginner',
                'tags' => ['words'],
                'features' => ['dictionary'],
                'level' => GameLevel::BEGINNER,
            ],
            [
                'key' => 'anagram-rush',
                'nameKey' => 'anagram-rush',
                'descriptionKey' => 'anagram-rush',
                'img' => '/img/game/anagram-rush.png',
                'icon' => 'mdi-format-letter-case',
                'category' => 'smart-games',
                'subcategory' => 'words-language',
                'component' => null,
                'supportedModes' => [],
                'categoryKey' => 'smart-games',
                'subcategoryKey' => 'words-language',
                'difficultyKey' => 'intermediate',
                'tags' => ['words', 'speed'],
                'features' => ['daily-challenge'],
                'level' => GameLevel::INTERMEDIATE,
            ],
            [
                'key' => 'number-grid',
                'nameKey' => 'number-grid',
                'descriptionKey' => 'number-grid',
                'img' => '/img/game/number-grid.png',
                'icon' => 'mdi-numeric',
                'category' => 'smart-games',
                'subcategory' => 'logic-brain',
                'component' => 'NumberGrid',
                'supportedModes' => ['solo'],
                'categoryKey' => 'smart-games',
                'subcategoryKey' => 'logic-brain',
                'difficultyKey' => 'advanced',
                'tags' => ['logic', 'numbers'],
                'features' => ['hints'],
                'level' => GameLevel::ADVANCED,
            ],
            [
                'key' => 'color-reactor',
                'nameKey' => 'color-reactor',
                'descriptionKey' => 'color-reactor',
                'img' => '/img/game/color-reactor.png',
                'icon' => 'mdi-palette-outline',
                'category' => 'arcade',
                'subcategory' => 'reflex-arcade',
                'component' => null,
                'supportedModes' => ['solo'],
                'categoryKey' => 'arcade',
                'subcategoryKey' => 'reflex-arcade',
                'difficultyKey' => 'intermediate',
                'tags' => ['reflex'],
                'features' => ['combo'],
                'level' => GameLevel::INTERMEDIATE,
            ],
            [
                'key' => 'sky-run',
                'nameKey' => 'sky-run',
                'descriptionKey' => 'sky-run',
                'img' => '/img/game/sky-run.png',
                'icon' => 'mdi-run-fast',
                'category' => 'arcade',
                'subcategory' => 'runner-arcade',
                'component' => 'SkyRun',
                'supportedModes' => ['solo', 'endless'],
                'categoryKey' => 'arcade',
                'subcategoryKey' => 'runner-arcade',
                'difficultyKey' => null,
                'tags' => [],
                'features' => [],
                'level' => GameLevel::BEGINNER,
            ],
        ];

        foreach ($games as $item) {
            $game = (new Game())
                ->setKey($item['key'])
                ->setNameKey($item['nameKey'])
                ->setDescriptionKey($item['descriptionKey'])
                ->setImg($item['img'])
                ->setIcon($item['icon'])
                ->setCategory($this->getReference('GameCategory-' . $item['category'], GameCategory::class))
                ->setSubCategory($this->getReference('GameSubCategory-' . $item['subcategory'], GameSubCategory::class))
                ->setComponent($item['component'])
                ->setSupportedModes($item['supportedModes'])
                ->setCategoryKey($item['categoryKey'])
                ->setSubcategoryKey($item['subcategoryKey'])
                ->setDifficultyKey($item['difficultyKey'])
                ->setTags($item['tags'])
                ->setFeatures($item['features'])
                ->setLevel($item['level'])
                ->setStatus(GameStatus::ACTIVE)
                ->setMetadata([]);
            $this->forceUuid($game, 'game-' . $item['key']);

            $manager->persist($game);
            $this->addReference('Game-' . $item['key'], $game);
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
