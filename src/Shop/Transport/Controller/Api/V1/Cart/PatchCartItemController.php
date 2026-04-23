<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Cart;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\CartItem;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\CartItemRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Transport\Controller\Api\V1\Input\Cart\CartInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Cart\PatchCartItemInput;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
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
final readonly class PatchCartItemController
{
    public function __construct(
        private Security $security,
        private ShopRepository $shopRepository,
        private CartItemRepository $cartItemRepository,
        private CartService $cartService,
        private CartInputValidator $cartInputValidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws JsonException
     */
    #[Route('/v1/shop/carts/{shopId}/items/{itemId}', methods: [Request::METHOD_PATCH])]
        public function __invoke(string $applicationSlug, string $shopId, string $itemId, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
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

        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = PatchCartItemInput::fromArray($payload);
        $validationResponse = $this->cartInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $quantity = $input->quantity;

        $cart = $this->cartService->updateItemQuantity($cart, $item, $quantity);

        return new JsonResponse($this->cartService->serializeCart($cart));
    }
}
