<?php

declare(strict_types=1);

namespace App\Game\Transport\Controller\Api\V1;

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
final readonly class GameCategoryController
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/game-categories', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'List game categories with nested sub-categories.',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Game categories list.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '21000000-0000-1000-8000-000000000001'),
                            new OA\Property(property: 'key', type: 'string', example: 'cards'),
                            new OA\Property(property: 'nameKey', type: 'string', example: 'gamePage.catalog.categories.cards.name'),
                            new OA\Property(property: 'descriptionKey', type: 'string', example: 'gamePage.catalog.categories.cards.description'),
                            new OA\Property(property: 'img', type: 'string', nullable: true, example: '/img/game/card-game.png'),
                            new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'mdi-cards-playing-outline'),
                            new OA\Property(
                                property: 'subCategories',
                                type: 'array',
                                items: new OA\Items(
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'string', format: 'uuid', example: '21000000-0000-1000-8000-000000000011'),
                                        new OA\Property(property: 'key', type: 'string', example: 'classic-cards'),
                                        new OA\Property(property: 'nameKey', type: 'string', example: 'gamePage.catalog.subCategories.classicCards.name'),
                                        new OA\Property(property: 'descriptionKey', type: 'string', example: 'gamePage.catalog.subCategories.classicCards.description'),
                                        new OA\Property(property: 'img', type: 'string', nullable: true, example: '/img/game/classic-card.png'),
                                        new OA\Property(property: 'icon', type: 'string', nullable: true, example: 'mdi-cards-outline'),
                                    ],
                                ),
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(response: 401, description: 'Authentication required.'),
            new OA\Response(response: 403, description: 'Access denied.'),
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
                'id' => $category->getId(),
                'key' => $category->getKey(),
                'nameKey' => $category->getNameKey(),
                'descriptionKey' => $category->getDescriptionKey() ?? '',
                'img' => $category->getImg(),
                'icon' => $category->getIcon(),
                'subCategories' => array_map(static fn (GameSubCategory $subCategory): array => [
                    'id' => $subCategory->getId(),
                    'key' => $subCategory->getKey(),
                    'nameKey' => $subCategory->getNameKey(),
                    'descriptionKey' => $subCategory->getDescriptionKey() ?? '',
                    'img' => $subCategory->getImg(),
                    'icon' => $subCategory->getIcon(),
                ], $subCategories),
            ];
        }, $categories);

        return new JsonResponse($payload);
    }
}

