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

