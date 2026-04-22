<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

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
final readonly class GeneralPaymentWebhookController
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
    #[OA\Parameter(
        name: 'provider',
        in: 'query',
        required: false,
        description: 'Optional provider hint used to route webhook events.',
        schema: new OA\Schema(type: 'string', enum: ['paypal', 'stripe', 'mock'], example: 'stripe'),
    )]
    #[OA\Post(
        summary: 'Receive provider webhook notifications for global shop payments.',
        description: 'Manual /api/doc optional step 6/6: POST /v1/shop/general/payments/webhook?provider=stripe. Reuse providerReference=pi_3QyQkL2x8d9 from step 4 to simulate asynchronous provider callbacks.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: 'object',
                description: 'Provider webhook payload (shape depends on provider).',
                examples: [
                    new OA\Examples(
                        example: 'manual_step_6_webhook_input',
                        summary: 'Optional step 6 input - Stripe event linked to providerReference from step 4',
                        value: [
                            'id' => 'evt_1QyQtx2x8d9',
                            'type' => 'payment_intent.succeeded',
                            'data' => [
                                'object' => [
                                    'id' => 'pi_3QyQkL2x8d9',
                                    'status' => 'succeeded',
                                ],
                            ],
                        ],
                    ),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_OK,
        description: 'Webhook processed and transaction updated.',
        content: new OA\JsonContent(example: [
            'processed' => true,
            'transactionId' => 'txn_8f96897f-bf44-4ed5-b2e8-cd8b64ac9ef8',
            'providerReference' => 'pi_3QyQkL2x8d9',
            'status' => 'succeeded',
        ]),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_ACCEPTED,
        description: 'Webhook accepted but no matching transaction was updated.',
        content: new OA\JsonContent(example: ['processed' => false]),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload.',
        content: new OA\JsonContent(example: [
            'message' => 'Validation failed.',
            'errors' => [[
                'field' => 'payload',
                'message' => 'Invalid JSON payload.',
                'code' => 'INVALID_JSON',
            ]],
        ]),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Not used for this public endpoint.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Signature verification failed.')]
    #[OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Provider not found.')]
    public function __invoke(Request $request): JsonResponse
    {
        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $signature = $request->headers->get('x-signature');
        $provider = $request->query->get('provider');
        $transaction = $this->paymentService->processWebhook($payload, $signature, is_string($provider) ? $provider : null);

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
