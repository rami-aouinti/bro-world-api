<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\ApplicationProduct;

use App\Shop\Application\Service\ProductApplicationListService;
use App\Shop\Application\Service\ShopApplicationResolverService;
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
final readonly class ListApplicationProductsController
{
    public function __construct(
        private ShopApplicationResolverService $shopApplicationResolverService,
        private ProductApplicationListService $productApplicationListService,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/products', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $shop = $this->shopApplicationResolverService->resolveOrCreateShopByApplicationSlug($applicationSlug);

        return new JsonResponse($this->productApplicationListService->getList($request, $applicationSlug, $shop));
    }
}
