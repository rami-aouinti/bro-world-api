<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\General;

use App\Shop\Application\Service\PaymentService;
use App\Shop\Transport\Controller\Api\V1\Input\Payment\ConfirmPaymentInput;
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

#[AsController]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[OA\Tag(name: 'Shop')]
final readonly class ConfirmGeneralPaymentController
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
    #[Route('/v1/shop/general/orders/{orderId}/payment-confirm', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Confirm a previously created payment intent for a global order.',
        description: 'Independent from any application slug: validates provider callback data and updates transaction status for a global shop order.',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['providerReference'],
                properties: [
                    new OA\Property(property: 'providerReference', type: 'string', example: 'pi_3QyQkL2x8d9'),
                    new OA\Property(property: 'providerPayload', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'string'), nullable: true),
                ],
                examples: [
                    new OA\Examples(
                        example: 'confirm_payment',
                        summary: 'Confirm Stripe payment',
                        value: [
                            'providerReference' => 'pi_3QyQkL2x8d9',
                            'providerPayload' => [
                                'event' => 'payment_intent.succeeded',
                                'paymentMethod' => 'stripe',
                                'provider' => 'stripe',
                            ],
                        ],
                    ),
                ],
            ),
        ),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_OK,
        description: 'Payment confirmed.',
        content: new OA\JsonContent(example: [
            'id' => 'txn_8f96897f-bf44-4ed5-b2e8-cd8b64ac9ef8',
            'orderId' => 'ord_8cb7be4f-2d27-430d-bc16-5b9fc4f2ef1e',
            'provider' => 'stripe',
            'providerReference' => 'pi_3QyQkL2x8d9',
            'status' => 'succeeded',
        ]),
    )]
    #[OA\Response(
        response: JsonResponse::HTTP_BAD_REQUEST,
        description: 'Invalid JSON payload or invalid confirmation fields.',
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
                    example: 'missing_provider_reference',
                    summary: 'Missing provider reference',
                    value: [
                        'message' => 'Validation failed.',
                        'errors' => [[
                            'field' => 'providerReference',
                            'message' => 'This value should not be blank.',
                            'code' => 'NOT_BLANK_ERROR',
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
        description: 'Order or transaction not found.',
        content: new OA\JsonContent(example: ['message' => 'Order not found.']),
    )]
    public function __invoke(string $orderId, Request $request): JsonResponse
    {
        try {
            $payload = (array)json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return ValidationResponseFactory::invalidJson();
        }

        $input = ConfirmPaymentInput::fromArray($payload);
        $validationResponse = $this->paymentInputValidator->validate($input);
        if ($validationResponse instanceof JsonResponse) {
            return $validationResponse;
        }

        $transaction = $this->paymentService->confirmPayment(null, $orderId, $input->providerReference, $payload);

        return new JsonResponse([
            'id' => $transaction->getId(),
            'orderId' => $transaction->getOrder()?->getId(),
            'provider' => $transaction->getProvider(),
            'providerReference' => $transaction->getProviderReference(),
            'status' => $transaction->getStatus()->value,
        ]);
    }
}
