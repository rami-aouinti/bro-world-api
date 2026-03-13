<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Payment;

use App\Shop\Application\Service\MoneyFormatter;
use App\Shop\Application\Service\PaymentService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Shop')]
final readonly class CreatePaymentIntentController
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/orders/{orderId}/payment-intent', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        security: [[
            'Bearer' => [],
        ]],
        summary: 'Create a payment intent for an order (private endpoint, full authentication required).',
    )]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Forbidden. The order does not belong to the authenticated user or requested application.')]
    public function __invoke(string $applicationSlug, string $orderId): JsonResponse
    {
        $transaction = $this->paymentService->createPaymentIntent($applicationSlug, $orderId);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
            'amount' => MoneyFormatter::toApiAmount($transaction->getAmount()),
            'currency' => $transaction->getCurrency(),
        ], JsonResponse::HTTP_CREATED);
    }
}
