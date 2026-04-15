<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\ShopApiSerializer;
use App\Shop\Domain\Entity\Category;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class GetGeneralCategoryController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/v1/shop/general/categories/{category}', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Get one category in global shop scope')]
    public function __invoke(Category $category): JsonResponse
    {
        $shop = $this->shopRepository->findGlobalShop();
        if ($shop === null) {
            return new JsonResponse(['message' => 'Global shop not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $scopedCategory = $this->categoryRepository->findOneByIdAndShop($category->getId(), $shop);
        if (!$scopedCategory instanceof Category) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'category' => ShopApiSerializer::serializeCategory($scopedCategory),
        ]);
    }
}
