<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\ShopApiSerializer;
use App\Shop\Domain\Entity\Category;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class ListGeneralCategoriesController
{
    public function __construct(
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[OA\Get(summary: 'List categories for global shop scope')]
    public function __invoke(): JsonResponse
    {
        $items = array_map(
            static fn (Category $category): array => ShopApiSerializer::serializeCategory($category),
            $this->categoryRepository->findGlobalCategories(),
        );

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
