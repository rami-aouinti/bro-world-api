<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\CartItem;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\CartItemRepository;
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
final readonly class DeleteGeneralCartItemController
{
    public function __construct(
        private ShopRepository $shopRepository,
        private CartItemRepository $cartItemRepository,
        private CartService $cartService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[OA\Parameter(name: 'shopId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Remove one item from the authenticated user cart in global shop scope.',
        description: 'Independent from any application slug: removes an item from the active cart of the authenticated user.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(response: JsonResponse::HTTP_OK, description: 'Cart item removed.')]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Authenticated user required.')]
    #[OA\Response(
        response: JsonResponse::HTTP_NOT_FOUND,
        description: 'Shop or cart item not found.',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(example: 'shop_not_found', value: ['message' => 'Shop not found.']),
                new OA\Examples(example: 'item_not_found', value: ['message' => 'Cart item not found.']),
            ],
        ),
    )]
    public function __invoke(string $shopId, string $itemId, User $loggedInUser): JsonResponse
    {
        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            return new JsonResponse([
                'message' => 'Shop not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->getOrCreateActiveCart($loggedInUser, $shop);

        $item = $this->cartItemRepository->find($itemId);
        if (!$item instanceof CartItem || $item->getCart()?->getId() !== $cart->getId()) {
            return new JsonResponse([
                'message' => 'Cart item not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->removeItem($cart, $item);

        return new JsonResponse($this->cartService->serializeCart($cart));
    }
}
