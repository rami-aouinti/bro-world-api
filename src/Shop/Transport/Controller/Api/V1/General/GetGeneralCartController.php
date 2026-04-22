<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
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
final readonly class GetGeneralCartController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CartService $cartService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[OA\Parameter(name: 'shopId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3'))]
    #[OA\Get(
        description: 'Manual /api/doc chain step 2/6: GET /v1/shop/general/carts/{shopId}. Reuse shopId=f95da407-b9f0-4d5f-a14e-15c4b22af6e3 from step 1 and verify cart totals before checkout in step 3.',
        summary: 'Get the authenticated user active cart for a global shop.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_OK,
        description: 'Cart retrieved.',
        content: new OA\JsonContent(example: [
            'id' => 'cart_8c501b14-4c4e-4f74-9ff7-cce9dd0cf1f7',
            'shopId' => 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3',
            'userId' => 'aa5f0b80-6a57-4fa5-ab8f-321723ebfd6a',
            'subtotal' => 129.9,
            'itemsCount' => 2,
            'currencyCode' => 'EUR',
            'updatedAt' => '2026-04-15T10:10:20+00:00',
            'items' => [[
                'id' => 'item_922da95e-212f-435f-b20b-ced40f74f8dc',
                'productId' => '8b673f1d-8f2f-4a81-b5e8-6f2f14b26626',
                'quantity' => 2,
                'unitPriceSnapshot' => 64.95,
                'lineTotal' => 129.9,
            ]],
        ]),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Authenticated user required.')]
    #[OA\Response(
        response: JsonResponse::HTTP_NOT_FOUND,
        description: 'Shop not found.',
        content: new OA\JsonContent(example: ['message' => 'Shop not found.']),
    )]
    public function __invoke(string $shopId, User $loggedInUser): JsonResponse
    {
        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            return new JsonResponse([
                'message' => 'Shop not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->getOrCreateActiveCart($loggedInUser, $shop);
        $cart = $this->cartService->recalculate($cart);

        return new JsonResponse($this->cartService->serializeCart($cart));
    }
}
