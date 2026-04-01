<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

use App\Game\Domain\Entity\Game;
use App\Game\Domain\Entity\GameCategory;
use App\Game\Domain\Entity\GameSubCategory;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Game')]
final readonly class PublicGameCatalogController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/public/game-catalog', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'GET /v1/public/game-catalog',
        security: [],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Public game catalog.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', example: 'card'),
                            new OA\Property(property: 'nameKey', type: 'string', example: 'Card'),
                            new OA\Property(property: 'descriptionKey', type: 'string', example: 'Card games'),
                            new OA\Property(property: 'img', type: 'string', nullable: true, example: '/img/categories/card.png'),
                            new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'spade'),
                            new OA\Property(
                                property: 'subCategories',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', example: 'classic-card'),
                                        new OA\Property(property: 'nameKey', type: 'string', example: 'Classic card games'),
                                        new OA\Property(property: 'descriptionKey', type: 'string', example: 'Classic card game modes'),
                                        new OA\Property(property: 'img', type: 'string', nullable: true, example: '/img/subcategories/classic-card.png'),
                                        new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'cards'),
                                        new OA\Property(
                                            property: 'games',
                                            type: 'array',
                                            items: new OA\Items(
                                                type: 'object',
                                                properties: [
                                                    new OA\Property(property: 'id', type: 'string', example: 'card-memory-duel'),
                                                    new OA\Property(property: 'nameKey', type: 'string', example: 'Memory Duel'),
                                                    new OA\Property(property: 'descriptionKey', type: 'string', example: 'Fast card battles'),
                                                    new OA\Property(property: 'img', type: 'string', nullable: true, example: '/img/games/memory-duel.png'),
                                                    new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'memory'),
                                                    new OA\Property(property: 'component', type: 'string', nullable: true, example: 'MemoryDuel'),
                                                    new OA\Property(property: 'supportedModes', type: 'array', items: new OA\Items(type: 'string', example: 'solo')),
                                                    new OA\Property(property: 'categoryKey', type: 'string', nullable: true, example: 'card'),
                                                    new OA\Property(property: 'subcategoryKey', type: 'string', nullable: true, example: 'classic-card'),
                                                    new OA\Property(property: 'difficultyKey', type: 'string', nullable: true, example: 'beginner'),
                                                    new OA\Property(property: 'tags', type: 'array', items: new OA\Items(type: 'string', example: 'memory')),
                                                    new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string', example: 'multiplayer')),
                                                ],
                                            ),
                                        ),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ),
            ),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $categories = $this->entityManager->getRepository(GameCategory::class)->findBy([], ['createdAt' => 'ASC', 'id' => 'ASC']);

        $payload = array_map(function (GameCategory $category): array {
            $subCategories = $this->entityManager->getRepository(GameSubCategory::class)->findBy(
                ['category' => $category],
                ['createdAt' => 'ASC', 'id' => 'ASC'],
            );

            return [
                'id' => $category->getKey(),
                'nameKey' => $category->getNameKey(),
                'descriptionKey' => $category->getDescriptionKey() ?? '',
                'img' => $category->getImg(),
                'icon' => $category->getIcon(),
                'subCategories' => array_map(function (GameSubCategory $subCategory): array {
                    $games = $this->entityManager->getRepository(Game::class)->findBy(
                        ['subCategory' => $subCategory],
                        ['createdAt' => 'ASC', 'id' => 'ASC'],
                    );

                    return [
                        'id' => $subCategory->getKey(),
                        'nameKey' => $subCategory->getNameKey(),
                        'descriptionKey' => $subCategory->getDescriptionKey() ?? '',
                        'img' => $subCategory->getImg(),
                        'icon' => $subCategory->getIcon(),
                        'games' => array_map(static fn (Game $game): array => self::normalizeGame($game), $games),
                    ];
                }, $subCategories),
            ];
        }, $categories);

        return new JsonResponse($payload);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeGame(Game $game): array
    {
        $payload = [
            'id' => $game->getKey(),
            'nameKey' => $game->getNameKey(),
            'descriptionKey' => $game->getDescriptionKey() ?? '',
            'img' => $game->getImg(),
            'icon' => $game->getIcon(),
            'component' => $game->getComponent(),
            'supportedModes' => $game->getSupportedModes(),
        ];

        $categoryKey = $game->getCategoryKey() ?? $game->getCategory()?->getKey();
        if ($categoryKey !== null) {
            $payload['categoryKey'] = $categoryKey;
        }

        $subcategoryKey = $game->getSubcategoryKey() ?? $game->getSubCategory()?->getKey();
        if ($subcategoryKey !== null) {
            $payload['subcategoryKey'] = $subcategoryKey;
        }

        if ($game->getDifficultyKey() !== null) {
            $payload['difficultyKey'] = $game->getDifficultyKey();
        }

        if ($game->getTags() !== []) {
            $payload['tags'] = $game->getTags();
        }

        if ($game->getFeatures() !== []) {
            $payload['features'] = $game->getFeatures();
        }

        return $payload;
    }
}
