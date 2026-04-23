<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\ProductListService;
use App\Shop\Application\Service\ShopApiSerializer;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Domain\Entity\Category;
use App\Shop\Infrastructure\Repository\CategoryRepository;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class GetGeneralShopController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private CategoryRepository $categoryRepository,
        private ProductListService $productListService,
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    #[Route('/v1/shop/general', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Get global shop overview', security: [])]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
    #[OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    public function __invoke(Request $request): JsonResponse
    {
        $shop = $this->shopApplicationResolverService->resolveGlobalShop();
        $categories = array_map(
            static fn (Category $category): array => ShopApiSerializer::serializeCategory($category),
            $this->categoryRepository->findGlobalCategories(),
        );

        return new JsonResponse([
            'shop' => ShopApiSerializer::serializeShop($shop),
            'categories' => $categories,
            'products' => $this->productListService->getGlobalList($request),
        ]);
    }
}
