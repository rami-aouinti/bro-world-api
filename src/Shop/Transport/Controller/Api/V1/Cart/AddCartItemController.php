<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Cart;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ProductRepository;
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
final readonly class AddCartItemController
{
    public function __construct(
        private Security $security,
        private ShopRepository $shopRepository,
        private ProductRepository $productRepository,
        private CartService $cartService,
    ) {
    }

    #[Route('/v1/shop/carts/{shopId}/items', methods: [Request::METHOD_POST])]
    public function __invoke(string $shopId, Request $request): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Authenticated user required.');
        }

        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            return new JsonResponse(['message' => 'Shop not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = (array) json_decode((string) $request->getContent(), true);
        $productId = (string) ($payload['productId'] ?? '');
        $quantity = max(1, (int) ($payload['quantity'] ?? 1));

        $product = $this->productRepository->find($productId);
        if (!$product instanceof Product || $product->getShop()?->getId() !== $shop->getId()) {
            return new JsonResponse(['message' => 'Product not found for this shop.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->getOrCreateActiveCart($user, $shop);
        $cart = $this->cartService->addProduct($cart, $product, $quantity);

        return new JsonResponse($this->cartService->serializeCart($cart), JsonResponse::HTTP_CREATED);
    }
}
