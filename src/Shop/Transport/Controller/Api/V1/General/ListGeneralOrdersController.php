<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\MoneyFormatter;
use App\Shop\Domain\Entity\Order;
use App\Shop\Domain\Entity\OrderItem;
use App\Shop\Infrastructure\Repository\OrderRepository;
use App\User\Domain\Entity\User;
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
final readonly class ListGeneralOrdersController
{
    public function __construct(
        private OrderRepository $orderRepository,
    ) {
    }

    #[OA\Get(
        summary: 'List authenticated user orders in global shop scope.',
        security: [['Bearer' => []]],
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_OK,
        description: 'Orders retrieved.',
        content: new OA\JsonContent(example: [
            'items' => [[
                'id' => 'ord_8cb7be4f-2d27-430d-bc16-5b9fc4f2ef1e',
                'shopId' => 'f95da407-b9f0-4d5f-a14e-15c4b22af6e3',
                'userId' => 'aa5f0b80-6a57-4fa5-ab8f-321723ebfd6a',
                'status' => 'pending_payment',
                'subtotal' => 129.9,
                'billingAddress' => '42 Rue des Fleurs, 75001 Paris, FR',
                'shippingAddress' => '15 Avenue Victor Hugo, 75016 Paris, FR',
                'email' => 'alice.martin@example.com',
                'phone' => '+33123456789',
                'shippingMethod' => 'express',
                'createdAt' => '2026-04-15T10:12:55+00:00',
                'updatedAt' => '2026-04-15T10:20:21+00:00',
                'items' => [[
                    'id' => 'item_922da95e-212f-435f-b20b-ced40f74f8dc',
                    'productId' => '8b673f1d-8f2f-4a81-b5e8-6f2f14b26626',
                    'quantity' => 2,
                    'unitPriceSnapshot' => 64.95,
                    'lineTotal' => 129.9,
                    'productNameSnapshot' => 'Starter CRM Package',
                    'productSkuSnapshot' => 'CRM-STARTER-001',
                ]],
            ]],
        ]),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Authenticated user required.')]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        $orders = $this->orderRepository->findBy(['user' => $loggedInUser], ['createdAt' => 'DESC']);

        return new JsonResponse([
            'items' => array_map(fn (Order $order): array => $this->serializeOrder($order), $orders),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrder(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'shopId' => $order->getShop()?->getId(),
            'userId' => $order->getUser()?->getId(),
            'status' => $order->getStatus()->value,
            'subtotal' => MoneyFormatter::toApiAmount($order->getSubtotal()),
            'billingAddress' => $order->getBillingAddress(),
            'shippingAddress' => $order->getShippingAddress(),
            'email' => $order->getEmail(),
            'phone' => $order->getPhone(),
            'shippingMethod' => $order->getShippingMethod(),
            'createdAt' => $order->getCreatedAt()?->format(DATE_ATOM),
            'updatedAt' => $order->getUpdatedAt()?->format(DATE_ATOM),
            'items' => array_map(fn (OrderItem $item): array => $this->serializeOrderItem($item), $order->getItems()->toArray()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderItem(OrderItem $item): array
    {
        return [
            'id' => $item->getId(),
            'productId' => $item->getProduct()?->getId(),
            'quantity' => $item->getQuantity(),
            'unitPriceSnapshot' => MoneyFormatter::toApiAmount($item->getUnitPriceSnapshot()),
            'lineTotal' => MoneyFormatter::toApiAmount($item->getLineTotal()),
            'productNameSnapshot' => $item->getProductNameSnapshot(),
            'productSkuSnapshot' => $item->getProductSkuSnapshot(),
        ];
    }
}
