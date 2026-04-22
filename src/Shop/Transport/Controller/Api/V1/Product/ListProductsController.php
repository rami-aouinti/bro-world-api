<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Product;

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
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListProductsController
{
    private const LEGACY_ROUTE_WARNING = 'Deprecated endpoint: use /v1/shop/legacy/products instead.';

    public function __construct(
        private ProductListService $productListService
    ) {
    }

    /**
     * @throws JsonException
     * @throws InvalidArgumentException
     */
    #[Route('/v1/shop/legacy/products', methods: [Request::METHOD_GET])]
    #[OA\Get(
        deprecated: true,
        summary: 'Legacy list products endpoint',
        description: 'Deprecated: migrate to /v1/shop/products.',
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $response = new JsonResponse($this->productListService->getList($request));
        $response->headers->set('Deprecation', 'true');
        $response->headers->set('Sunset', 'Wed, 31 Dec 2026 23:59:59 GMT');
        $response->headers->set('Warning', sprintf('299 - "%s"', self::LEGACY_ROUTE_WARNING));

        return $response;
    }
}
