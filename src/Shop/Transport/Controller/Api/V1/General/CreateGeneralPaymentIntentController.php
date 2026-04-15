<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\MoneyFormatter;
use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\CreatePaymentIntentInput;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\PaymentInputValidator;
use App\Shop\Transport\Controller\Api\V1\Input\Support\ValidationResponseFactory;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function trim;

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Shop')]
final readonly class CreateGeneralPaymentIntentController
{
    public function __construct(
        private PaymentService $paymentService,
        private PaymentInputValidator $paymentInputValidator,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/shop/general/orders/{orderId}/payment-intent', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Create a payment intent for an existing global-scope order.',
        description: 'Independent from application context: creates a transaction intent for a global shop order using the selected payment provider and method.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'provider', type: 'string', enum: ['paypal', 'stripe', 'mock'], example: 'stripe'),
                    new OA\Property(property: 'paymentMethod', type: 'string', enum: ['paypal', 'stripe', 'mock'], example: 'stripe'),
                ],
                type: 'object',
                examples: [
                    new OA\Examples(
                        example: 'create_payment_intent',
                        summary: 'Create payment intent with Stripe card flow',
                        value: [
                            'provider' => 'stripe',
                            'paymentMethod' => 'stripe',
                        ],
                    ),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_CREATED,
        description: 'Payment intent created.',
        content: new OA\JsonContent(example: [
            'id' => 'txn_8f96897f-bf44-4ed5-b2e8-cd8b64ac9ef8',
            'orderId' => 'ord_8cb7be4f-2d27-430d-bc16-5b9fc4f2ef1e',
            'provider' => 'stripe',
            'providerReference' => 'pi_3QyQkL2x8d9',
            'status' => 'requires_confirmation',
            'amount' => 129.9,
            'currency' => 'EUR',
        ]),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload or invalid provider/payment method value.',
        content: new OA\JsonContent(
            examples: [
                new OA\Examples(
                    example: 'invalid_json',
                    summary: 'Malformed JSON payload',
                    value: [
                        'message' => 'Validation failed.',
                        'errors' => [[
                            'field' => 'payload',
                            'message' => 'Invalid JSON payload.',
                            'code' => 'INVALID_JSON',
                        ]],
                    ],
                ),
                new OA\Examples(
                    example: 'invalid_provider',
                    summary: 'Unsupported provider',
                    value: [
                        'message' => 'Validation failed.',
                        'errors' => [[
                            'field' => 'provider',
                            'message' => 'Choose a valid provider: paypal, stripe or mock.',
                            'code' => 'INVALID_PROVIDER',
                        ]],
                    ],
                ),
            ],
        ),
    )]
    #[OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Missing or invalid Bearer token.')]
    #[OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'The order does not belong to the authenticated user.')]
    #[OA\Response(
        response: JsonResponse::HTTP_NOT_FOUND,
        description: 'Order not found.',
        content: new OA\JsonContent(example: ['message' => 'Order not found.']),
    )]
    public function __invoke(string $orderId, Request $request): JsonResponse
    {
        $rawContent = (string)$request->getContent();
        if (trim($rawContent) === '') {
            $payload = [];
        } else {
            try {
                $payload = (array)json_decode($rawContent, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return ValidationResponseFactory::invalidJson();
            }
        }

        $input = CreatePaymentIntentInput::fromArray($payload);
        $validationResponse = $this->paymentInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $transaction = $this->paymentService->createPaymentIntent(null, $orderId, $input->provider, $input->paymentMethod);

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
