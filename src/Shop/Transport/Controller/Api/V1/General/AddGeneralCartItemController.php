<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\CartService;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Shop\Transport\Controller\Api\V1\Input\Cart\AddCartItemInput;
use App\Shop\Transport\Controller\Api\V1\Input\Cart\CartInputValidator;
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
final readonly class AddGeneralCartItemController
{
    public function __construct(
        private Security $security,
        private ShopRepository $shopRepository,
        private ProductRepository $productRepository,
        private CartService $cartService,
        private CartInputValidator $cartInputValidator,
    ) {
    }

    /**
     * @throws ORMException
     * @throws JsonException
     * @throws OptimisticLockException
     */
    #[Route('/v1/shop/general/carts/{shopId}/items', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'shopId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid', example: 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3'))]
    #[OA\Post(
        description: 'Manual /api/doc chain step 1/6: POST /v1/shop/general/carts/{shopId}/items. Use shopId=f95da407-b9f0-4d5f-a14e-15c4b22af6e3, then reuse the same shopId in step 2 (GET cart) and step 3 (checkout).',
        summary: 'Add a product to the authenticated user cart in global shop scope.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                examples: [
                    new OA\Examples(
                        example: 'manual_step_1_add_item_input',
                        summary: 'Step 1 input - add product with chainable shopId',
                        value: [
                            'productId' => '8b673f1d-8f2f-4a81-b5e8-6f2f14b26626',
                            'quantity' => 2,
                        ],
                    ),
                ],
                required: ['productId', 'quantity'],
                properties: [
                    new OA\Property(property: 'productId', type: 'string', format: 'uuid', example: '8b673f1d-8f2f-4a81-b5e8-6f2f14b26626'),
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 1, example: 2),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_CREATED,
        description: 'Cart updated with new item.',
        content: new OA\JsonContent(example: [
            'id' => 'cart_8c501b14-4c4e-4f74-9ff7-cce9dd0cf1f7',
            'shopId' => 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3',
            'userId' => 'aa5f0b80-6a57-4fa5-ab8f-321723ebfd6a',
            'subtotal' => 129.9,
            'itemsCount' => 2,
            'currencyCode' => 'EUR',
            'updatedAt' => '2026-04-15T10:09:03+00:00',
            'items' => [[
                'id' => 'item_922da95e-212f-435f-b20b-ced40f74f8dc',
                'productId' => '8b673f1d-8f2f-4a81-b5e8-6f2f14b26626',
                'quantity' => 2,
                'unitPriceSnapshot' => 64.95,
                'lineTotal' => 129.9,
            ]],
        ]),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload or invalid quantity/product id.',
        content: new OA\JsonContent(example: [
            'message' => 'Validation failed.',
            'errors' => [[
                'field' => 'payload',
                'message' => 'Invalid JSON payload.',
                'code' => 'INVALID_JSON',
            ]],
        ]),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Authenticated user required.')]
    #[OA\Response(
        response: JsonResponse::HTTP_NOT_FOUND,
        description: 'Shop or product not found.',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'shop_not_found',
                    summary: 'Shop not found response',
                    value: [
                        'message' => 'Shop not found.',
                    ],
                ),
                new OA\Examples(
                    example: 'product_not_found',
                    summary: 'Product not found for this shop response',
                    value: [
                        'message' => 'Product not found for this shop.',
                    ],
                ),
            ],
        ),
    )]
    public function __invoke(User $loggedInUser ,string $shopId, Request $request): JsonResponse
    {

        $shop = $this->shopRepository->find($shopId);
        if (!$shop instanceof Shop) {
            return new JsonResponse([
                'message' => 'Shop not found.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = AddCartItemInput::fromArray($payload);
        $validationResponse = $this->cartInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $productId = $input->productId;
        $quantity = $input->quantity;

        $product = $this->productRepository->find($productId);
        if (!$product instanceof Product || $product->getShop()?->getId() !== $shop->getId()) {
            return new JsonResponse([
                'message' => 'Product not found for this shop.',
            ], JsonResponse::HTTP_NOT_FOUND);
        }

        $cart = $this->cartService->getOrCreateActiveCart($loggedInUser, $shop);
        $cart = $this->cartService->addProduct($cart, $product, $quantity);

        return new JsonResponse($this->cartService->serializeCart($cart), JsonResponse::HTTP_CREATED);
    }
}
