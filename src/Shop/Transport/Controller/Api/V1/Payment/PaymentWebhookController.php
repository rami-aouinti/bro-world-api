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
final readonly class PaymentWebhookController
{
    public function __construct(
        private PaymentService $paymentService,
    ) {
    }

    #[Route('/v1/shop/applications/{applicationSlug}/payments/webhook', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $payload = (array)json_decode((string)$request->getContent(), true);
        $signature = $request->headers->get('x-signature');

        $transaction = $this->paymentService->processWebhook($payload, $signature);

        if ($transaction === null) {
            return new JsonResponse([
                'processed' => false,
            ], JsonResponse::HTTP_ACCEPTED);
        }

        return new JsonResponse([
            'processed' => true,
            'transactionId' => $transaction->getId(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
