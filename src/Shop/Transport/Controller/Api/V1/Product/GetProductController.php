<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

use App\Shop\Application\Service\ProductListService;
use App\Shop\Application\Service\ShopApplicationResolverService;
use App\Shop\Application\Service\SimilarProductService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Infrastructure\Repository\ProductRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GetProductController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private ProductRepository $productRepository,
        private SimilarProductService $similarProductService,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products/{id}', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $id): JsonResponse
    {
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);
        $product = $this->productRepository->findOneByIdAndShop($id, $shop);
        if (!$product instanceof Product) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $similarProducts = array_map(
            static fn (Product $similarProduct): array => ProductListService::serializeProduct($similarProduct),
            $this->similarProductService->getSimilarProducts($product),
        );

        return new JsonResponse([
            'product' => ProductListService::serializeProduct($product),
            'similarProducts' => $similarProducts,
        ]);
    }
}
