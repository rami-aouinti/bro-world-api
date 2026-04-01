<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class GameCategoryControllerTest extends WebTestCase
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
    ];

    #[TestDox('GET /v1/game-categories returns HTTP 200 with exact nested categories and stable order (without games).')]
    public function testGameCategoriesReturnsExactStructureAndStableOrder(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/game-categories');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertSame($this->expectedPayload(), $payload);
        self::assertSame(['cards', 'board', 'smart-games', 'arcade'], array_column($payload, 'key'));

        foreach ($payload as $category) {
            foreach ($category['subCategories'] as $subCategory) {
                self::assertArrayNotHasKey('games', $subCategory);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function expectedPayload(): array
    {
        return [
            [
                'id' => self::UUID_BY_KEY['cards'],
                'key' => 'cards',
                'nameKey' => 'gamePage.catalog.categories.cards.name',
                'descriptionKey' => 'gamePage.catalog.categories.cards.description',
                'img' => '/img/game/card-game.png',
                'icon' => 'mdi-cards-playing-outline',
                'subCategories' => [
                    ['id' => self::UUID_BY_KEY['classic-cards'], 'key' => 'classic-cards', 'nameKey' => 'gamePage.catalog.subCategories.classicCards.name', 'descriptionKey' => 'gamePage.catalog.subCategories.classicCards.description', 'img' => '/img/game/classic-card.png', 'icon' => 'mdi-cards-outline'],
                    ['id' => self::UUID_BY_KEY['party-cards'], 'key' => 'party-cards', 'nameKey' => 'gamePage.catalog.subCategories.partyCards.name', 'descriptionKey' => 'gamePage.catalog.subCategories.partyCards.description', 'img' => '/img/game/party-card.png', 'icon' => 'mdi-party-popper'],
                ],
            ],
            [
                'id' => self::UUID_BY_KEY['board'],
                'key' => 'board',
                'nameKey' => 'gamePage.catalog.categories.board.name',
                'descriptionKey' => 'gamePage.catalog.categories.board.description',
                'img' => '/img/game/board-game.png',
                'icon' => 'mdi-checkerboard',
                'subCategories' => [
                    ['id' => self::UUID_BY_KEY['table-classic'], 'key' => 'table-classic', 'nameKey' => 'gamePage.catalog.subCategories.tableClassic.name', 'descriptionKey' => 'gamePage.catalog.subCategories.tableClassic.description', 'img' => '/img/game/card-game.png', 'icon' => 'mdi-gamepad-round-outline'],
                    ['id' => self::UUID_BY_KEY['family-board'], 'key' => 'family-board', 'nameKey' => 'gamePage.catalog.subCategories.familyBoard.name', 'descriptionKey' => 'gamePage.catalog.subCategories.familyBoard.description', 'img' => '/img/game/family-board.png', 'icon' => 'mdi-account-group-outline'],
                ],
            ],
            [
                'id' => self::UUID_BY_KEY['smart-games'],
                'key' => 'smart-games',
                'nameKey' => 'gamePage.catalog.categories.smartGames.name',
                'descriptionKey' => 'gamePage.catalog.categories.smartGames.description',
                'img' => '/img/game/smart-game.png',
                'icon' => 'mdi-brain',
                'subCategories' => [
                    ['id' => self::UUID_BY_KEY['logic'], 'key' => 'logic', 'nameKey' => 'gamePage.catalog.subCategories.logic.name', 'descriptionKey' => 'gamePage.catalog.subCategories.logic.description', 'img' => '/img/game/logic.png', 'icon' => 'mdi-lightbulb-on-outline'],
                    ['id' => self::UUID_BY_KEY['words-language'], 'key' => 'words-language', 'nameKey' => 'gamePage.catalog.subCategories.wordsLanguage.name', 'descriptionKey' => 'gamePage.catalog.subCategories.wordsLanguage.description', 'img' => '/img/game/words.png', 'icon' => 'mdi-alphabetical-variant'],
                    ['id' => self::UUID_BY_KEY['grids-puzzles'], 'key' => 'grids-puzzles', 'nameKey' => 'gamePage.catalog.subCategories.gridsPuzzles.name', 'descriptionKey' => 'gamePage.catalog.subCategories.gridsPuzzles.description', 'img' => '/img/game/puzzle.png', 'icon' => 'mdi-grid-large'],
                    ['id' => self::UUID_BY_KEY['brain-training'], 'key' => 'brain-training', 'nameKey' => 'gamePage.catalog.subCategories.brainTraining.name', 'descriptionKey' => 'gamePage.catalog.subCategories.brainTraining.description', 'img' => '/img/game/brain.png', 'icon' => 'mdi-head-cog-outline'],
                ],
            ],
            [
                'id' => self::UUID_BY_KEY['arcade'],
                'key' => 'arcade',
                'nameKey' => 'gamePage.catalog.categories.arcade.name',
                'descriptionKey' => 'gamePage.catalog.categories.arcade.description',
                'img' => '/img/game/arcade-game.png',
                'icon' => 'mdi-gamepad-variant-outline',
                'subCategories' => [
                    ['id' => self::UUID_BY_KEY['reaction-arcade'], 'key' => 'reaction-arcade', 'nameKey' => 'gamePage.catalog.subCategories.reactionArcade.name', 'descriptionKey' => 'gamePage.catalog.subCategories.reactionArcade.description', 'img' => '/img/game/card-game.png', 'icon' => 'mdi-lightning-bolt-outline'],
                    ['id' => self::UUID_BY_KEY['classic-arcade'], 'key' => 'classic-arcade', 'nameKey' => 'gamePage.catalog.subCategories.classicArcade.name', 'descriptionKey' => 'gamePage.catalog.subCategories.classicArcade.description', 'img' => '/img/game/card-game.png', 'icon' => 'mdi-ghost-outline'],
                ],
            ],
        ];
    }
}
