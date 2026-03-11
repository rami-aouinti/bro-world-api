<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Payment;

use App\Shop\Application\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Shop')]
final readonly class ConfirmPaymentController
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    #[Route('/v1/shop/orders/{orderId}/payment-confirm', methods: [Request::METHOD_POST])]
    public function __invoke(string $orderId, Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $providerReference = trim((string) ($payload['providerReference'] ?? ''));

        if ($providerReference === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'providerReference is required.');
        }

        $transaction = $this->paymentService->confirmPayment($orderId, $providerReference, $payload);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
