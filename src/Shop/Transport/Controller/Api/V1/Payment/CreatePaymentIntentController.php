<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Payment;

use App\Shop\Application\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class CreatePaymentIntentController
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    #[Route('/v1/shop/orders/{orderId}/payment-intent', methods: [Request::METHOD_POST])]
    public function __invoke(string $orderId): JsonResponse
    {
        $transaction = $this->paymentService->createPaymentIntent($orderId);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
            'amount' => $transaction->getAmount(),
            'currency' => $transaction->getCurrency(),
        ], JsonResponse::HTTP_CREATED);
    }
}
