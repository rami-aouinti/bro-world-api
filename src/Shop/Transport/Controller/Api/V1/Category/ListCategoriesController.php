<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Category;

use App\Shop\Application\Service\ShopApiSerializer;
use App\Shop\Application\Service\ShopApplicationResolverService;
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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListCategoriesController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private CategoryRepository $categoryRepository,
    ) {
    }

    #[Route('/v1/shop/categories', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        $items = array_map(static fn (Category $category): array => ShopApiSerializer::serializeCategory($category), $this->categoryRepository->findByShop($shop));

        return new JsonResponse([
            'items' => $items,
        ]);
    }
}
