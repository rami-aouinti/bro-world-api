<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Payment;

use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
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

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws JsonException
     */
    #[Route('/v1/shop/applications/{applicationSlug}/payments/webhook', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Handle payment provider webhook (public endpoint with strict signature verification).',
        security: [],
    )]
    #[OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Webhook signature is required in production.')]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Invalid webhook signature or payload.')]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);

        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }
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
