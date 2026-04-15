<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

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
final readonly class PatchGeneralCartItemController
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
    #[Route('/v1/shop/general/carts/{shopId}/items/{itemId}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'shopId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Parameter(name: 'itemId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Update quantity for one cart item in global shop scope.',
        description: 'Independent from application context: updates only the target cart line quantity for the authenticated user.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 3),
                ],
                examples: [
                    new OA\Examples(
                        example: 'patch_quantity',
                        summary: 'Set quantity to 3',
                        value: [
                            'quantity' => 3,
                        ],
                    ),
                ],
            ),
        ),
    )]
    #[OA\Response(response: JsonResponse::HTTP_OK, description: 'Cart item updated.')]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload or invalid quantity.',
        content: new OA\JsonContent(example: [
            'message' => 'Validation failed.',
            'errors' => [[
                'field' => 'quantity',
                'message' => 'This value should be greater than 0.',
                'code' => 'INVALID_QUANTITY',
            ]],
        ]),
    )]
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
    public function __invoke(string $shopId, string $itemId, Request $request): JsonResponse
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
