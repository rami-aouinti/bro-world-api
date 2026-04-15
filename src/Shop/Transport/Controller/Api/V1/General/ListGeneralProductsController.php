<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\ProductListService;
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
final readonly class ListGeneralProductsController
{
    public function __construct(
        private ProductListService $productListService,
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    #[Route('/v1/shop/general/products', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'List products for global shop scope')]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1))]
    #[OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'name', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'category', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'minPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: 0))]
    #[OA\Parameter(name: 'maxPrice', in: 'query', required: false, schema: new OA\Schema(type: 'number', format: 'float', minimum: 0))]
    #[OA\Parameter(name: 'promotion', in: 'query', required: false, description: 'Minimum promotion percentage to match (e.g. 20 means >=20%).', schema: new OA\Schema(type: 'integer', minimum: 0, maximum: 100))]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->productListService->getGlobalList($request));
    }
}
