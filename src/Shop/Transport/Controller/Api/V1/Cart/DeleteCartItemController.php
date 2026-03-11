<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Cart;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\CartItem;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\CartItemRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Shop')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class DeleteCartItemController
{
    public function __construct(
        private Security $security,
        private ShopRepository $shopRepository,
        private CartItemRepository $cartItemRepository,
        private CartService $cartService,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/carts/{shopId}/items/{itemId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $shopId, string $itemId): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Authenticated user required.');
        }

        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            return new JsonResponse([
                'message' => 'Shop not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->getOrCreateActiveCart($user, $shop);

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
