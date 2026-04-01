<?php

declare(strict_types=1);

namespace App\Tests\Application\Game\Transport\Controller\Api\V1;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameSubCategory;
use App\Game\Domain\Enum\GameLevel;
use App\Game\Domain\Enum\GameStatus;
use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class PublicGameCatalogControllerTest extends WebTestCase
{
    #[TestDox('Public game catalog endpoint returns categories with nested subcategories and games in fixture order.')]
    public function testPublicCatalogReturnsExpectedNestedStructureAndOrder(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $category = (new GameCategory())
            ->setKey('zzz-test-category')
            ->setNameKey('Test Category')
            ->setDescriptionKey('Test category description')
            ->setImg('cat.png')
            ->setIcon('cat-icon');

        $subCategoryA = (new GameSubCategory())
            ->setCategory($category)
            ->setKey('zzz-sub-a')
            ->setNameKey('Sub A')
            ->setDescriptionKey('Sub A description')
            ->setImg('sub-a.png')
            ->setIcon('sub-a-icon');

        $subCategoryB = (new GameSubCategory())
            ->setCategory($category)
            ->setKey('zzz-sub-b')
            ->setNameKey('Sub B')
            ->setDescriptionKey('Sub B description')
            ->setImg('sub-b.png')
            ->setIcon('sub-b-icon');

        $gameA = (new Game())
            ->setCategory($category)
            ->setSubCategory($subCategoryA)
            ->setKey('zzz-game-a')
            ->setNameKey('Game A')
            ->setDescriptionKey('Game A description')
            ->setImg('game-a.png')
            ->setIcon('game-a-icon')
            ->setComponent('GameAComponent')
            ->setSupportedModes(['solo', 'versus'])
            ->setCategoryKey('cat-key')
            ->setSubcategoryKey('sub-key-a')
            ->setDifficultyKey('advanced')
            ->setTags(['tag-a'])
            ->setFeatures(['feature-a'])
            ->setLevel(GameLevel::ADVANCED)
            ->setStatus(GameStatus::ACTIVE)
            ->setMetadata([]);

        $gameB = (new Game())
            ->setCategory($category)
            ->setSubCategory($subCategoryA)
            ->setKey('zzz-game-b')
            ->setNameKey('Game B')
            ->setDescriptionKey('Game B description')
            ->setImg('game-b.png')
            ->setIcon('game-b-icon')
            ->setComponent('GameBComponent')
            ->setSupportedModes(['coop'])
            ->setLevel(GameLevel::BEGINNER)
            ->setStatus(GameStatus::ACTIVE)
            ->setMetadata([]);

        $entityManager->persist($category);
        $entityManager->persist($subCategoryA);
        $entityManager->persist($subCategoryB);
        $entityManager->persist($gameA);
        $entityManager->persist($gameB);
        $entityManager->flush();

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/public/game-catalog');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);

        $categoryPayload = null;
        foreach ($payload as $item) {
            if (($item['id'] ?? null) === 'zzz-test-category') {
                $categoryPayload = $item;
                break;
            }
        }

        self::assertIsArray($categoryPayload);
        self::assertSame('Test Category', $categoryPayload['nameKey']);
        self::assertArrayHasKey('subCategories', $categoryPayload);

        $subCategories = $categoryPayload['subCategories'];
        self::assertIsArray($subCategories);
        self::assertCount(2, $subCategories);
        self::assertSame('zzz-sub-a', $subCategories[0]['id']);
        self::assertSame('zzz-sub-b', $subCategories[1]['id']);

        $gamesInSubA = $subCategories[0]['games'];
        self::assertIsArray($gamesInSubA);
        self::assertCount(2, $gamesInSubA);
        self::assertSame('zzz-game-a', $gamesInSubA[0]['id']);
        self::assertSame('zzz-game-b', $gamesInSubA[1]['id']);

        self::assertSame(['solo', 'versus'], $gamesInSubA[0]['supportedModes']);
        self::assertSame('cat-key', $gamesInSubA[0]['categoryKey']);
        self::assertSame('sub-key-a', $gamesInSubA[0]['subcategoryKey']);
        self::assertSame('advanced', $gamesInSubA[0]['difficultyKey']);
        self::assertSame(['tag-a'], $gamesInSubA[0]['tags']);
        self::assertSame(['feature-a'], $gamesInSubA[0]['features']);

        self::assertArrayNotHasKey('difficultyKey', $gamesInSubA[1]);
        self::assertArrayNotHasKey('tags', $gamesInSubA[1]);
        self::assertArrayNotHasKey('features', $gamesInSubA[1]);

        $gamesInSubB = $subCategories[1]['games'];
        self::assertIsArray($gamesInSubB);
        self::assertSame([], $gamesInSubB);
    }
}
